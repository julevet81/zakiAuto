<?php

namespace App\Http\Requests\AgentTransaction;

use App\Models\AgentTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', AgentTransaction::class);
    }

    /**
     * This endpoint is for MANUAL ledger entries only — commissions,
     * cash advances, manual corrections, etc. Entries that originate from
     * a customer payment or a treasury movement are created automatically
     * by CustomerPaymentController (store/remit), never through here, so
     * payment_id/transaction_id are not accepted as input.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'exists:agents,id'],
            'direction' => ['required', Rule::in([
                AgentTransaction::DIRECTION_IN,
                AgentTransaction::DIRECTION_OUT,
            ])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'attachment' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'يجب توضيح سبب الحركة اليدوية (مثل: عمولة طلب رقم...، سحب نقدي...)',
        ];
    }
}
