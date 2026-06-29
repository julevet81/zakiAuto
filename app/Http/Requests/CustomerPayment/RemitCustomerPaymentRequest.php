<?php

namespace App\Http\Requests\CustomerPayment;

use Illuminate\Foundation\Http\FormRequest;

class RemitCustomerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Marking a payment as remitted is a treasury-affecting action,
        // gated behind the same permission as creating agent ledger
        // entries (admin/super-admin only — agents cannot self-certify
        // that they've handed over cash, that must be confirmed by staff
        // who physically received it).
        return $this->user()->can('agent_transactions.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_date' => ['nullable', 'date'],
            'attachment' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
