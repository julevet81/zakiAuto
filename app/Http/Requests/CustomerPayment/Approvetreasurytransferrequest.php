<?php

namespace App\Http\Requests\CustomerPayment;

use Illuminate\Foundation\Http\FormRequest;

class ApproveTreasuryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Approving a pending transfer into the general treasury is a
        // treasury-affecting action — same permission tier already used
        // to confirm agent remittances (admin/super-admin only). Adjust
        // here if you'd rather gate this behind a dedicated permission.
        return $this->user()->can('agent_transactions.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'approval_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
