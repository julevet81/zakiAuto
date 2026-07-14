<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTreasuryDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('treasury.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'attachment'       => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required'           => 'المبلغ مطلوب',
            'amount.min'                => 'المبلغ يجب أن يكون أكبر من صفر',
            'transaction_date.required' => 'تاريخ العملية مطلوب',
        ];
    }
}
