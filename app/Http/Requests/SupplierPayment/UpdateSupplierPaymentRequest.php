<?php

namespace App\Http\Requests\SupplierPayment;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('supplier_payment'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['sometimes', 'required', 'integer', 'exists:batches,id'],
            'supplier_id' => ['sometimes', 'required', 'integer', 'exists:suppliers,id'],
            'amount_foreign' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'exchange_rate' => ['sometimes', 'required', 'numeric', 'min:0.0001'],
            'amount_local' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Same cross-field guard as Store: whichever batch/supplier pairing
     * results after this update must still be consistent. We fall back to
     * the existing record's values for whichever field isn't present in
     * this partial update.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $payment = $this->route('supplier_payment');

            $batchId = $this->input('batch_id', $payment?->batch_id);
            $supplierId = $this->input('supplier_id', $payment?->supplier_id);

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
