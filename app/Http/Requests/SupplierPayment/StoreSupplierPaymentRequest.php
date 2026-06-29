<?php

namespace App\Http\Requests\SupplierPayment;

use App\Models\Batch;
use App\Models\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SupplierPayment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'amount_foreign' => ['required', 'numeric', 'min:0.01'],
            'exchange_rate' => ['required', 'numeric', 'min:0.0001'],
            // amount_local is derived (amount_foreign * exchange_rate) when
            // not provided, but can be passed explicitly to override
            // rounding, e.g. when the accountant's paperwork uses a
            // slightly different rounded figure.
            'amount_local' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Cross-field validation: the chosen batch must actually belong to the
     * chosen supplier. Without this check it would be possible to record
     * a payment for Supplier A against a batch that belongs to Supplier B,
     * silently corrupting both suppliers' financial history.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $batchId = $this->input('batch_id');
            $supplierId = $this->input('supplier_id');

            if (! $batchId || ! $supplierId) {
                return;
            }

            $batch = Batch::find($batchId);

            if ($batch && (int) $batch->supplier_id !== (int) $supplierId) {
                $validator->errors()->add(
                    'batch_id',
                    'دفعة الاستيراد المحددة لا تعود لهذا المورد'
                );
            }
        });
    }
}
