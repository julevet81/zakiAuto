<?php

namespace App\Http\Requests\SupplierPayment;

use App\Models\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SupplierPayment::class);
    }

    /**
     * The user no longer selects which batch to pay — the system
     * distributes the amount automatically across the supplier's oldest
     * unpaid batches (FIFO). See SupplierPaymentController::store().
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id'   => ['required', 'integer', 'exists:suppliers,id'],
            'amount_foreign' => ['required', 'numeric', 'min:0.01'],
            'exchange_rate' => ['required', 'numeric', 'min:0.0001'],
            // amount_local may be overridden (e.g. accountant's rounded figure).
            // If absent it is computed as amount_foreign × exchange_rate.
            'amount_local'  => ['nullable', 'numeric', 'min:0'],
            'attachment'    => ['nullable', 'string', 'max:255'],
            'payment_date'  => ['required', 'date'],
            'notes'         => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required'   => 'يجب تحديد المورد',
            'amount_foreign.min'     => 'المبلغ يجب أن يكون أكبر من صفر',
            'exchange_rate.required' => 'يجب إدخال سعر الصرف',
            'payment_date.required'  => 'يجب تحديد تاريخ الدفع',
        ];
    }
}
