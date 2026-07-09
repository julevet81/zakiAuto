<?php

namespace App\Http\Requests\Expense;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
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
            'expense_type' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx', 'max:2048'],
            'expense_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * An expense should be tied to at least one of car/order, otherwise
     * it's a floating cost with no operational context — require one.
     */
    // public function withValidator(Validator $validator): void
    // {
    //     $validator->after(function (Validator $validator) {
    //         if (! $this->filled('car_id') && ! $this->filled('order_id')) {
    //             $validator->errors()->add('car_id', 'يجب ربط المصروف بسيارة أو بطلب على الأقل');
    //         }
    //     });
    // }
}
