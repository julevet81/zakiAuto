<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('expense'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'car_id' => ['nullable', 'integer', 'exists:cars,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'service_provider_id' => ['nullable', 'integer', 'exists:service_providers,id'],
            'expense_type' => ['sometimes', 'required', 'string', 'max:100'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'attachment' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
