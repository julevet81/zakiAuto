<?php

namespace App\Http\Requests\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ];
    }

    /**
     * An order can only have one invoice (modelled as hasOne in the Order
     * model). Reject generating a second one for the same order — if the
     * amounts changed, update the existing invoice instead.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $orderId = $this->input('order_id');

            if ($orderId && Invoice::where('order_id', $orderId)->exists()) {
                $validator->errors()->add('order_id', 'يوجد فاتورة لهذا الطلب مسبقًا');
            }
        });
    }
}
