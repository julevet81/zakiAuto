<?php

namespace App\Http\Requests\Car;

use App\Models\Car;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('car'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'batch_id' => ['sometimes', 'required', 'integer', 'exists:batches,id'],
            'supplier_id' => ['sometimes', 'required', 'integer', 'exists:suppliers,id'],
            'container_opener_id' => ['nullable', 'integer', 'exists:container_openers,id'],

            'brand' => ['sometimes', 'required', 'string', 'max:100'],
            'model' => ['sometimes', 'required', 'string', 'max:100'],
            'finition' => ['nullable', 'string', 'max:255'],
            'manufacture_year' => ['sometimes', 'required', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:50'],
            'vin' => [
                'nullable', 'string', 'max:50',
                Rule::unique('cars', 'vin')->ignore($this->route('car')),
            ],

            'foreign_purchase_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0'],

            'tracking_number' => ['nullable', 'string', 'max:255'],
            'container_no' => ['nullable', 'string', 'max:50'],
            'shipping_date' => ['nullable', 'date'],
            'arrival_date' => ['nullable', 'date', 'after_or_equal:shipping_date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:arrival_date'],

            'status' => ['nullable', Rule::in([
                Car::STATUS_AVAILABLE,
                Car::STATUS_RESERVED,
                Car::STATUS_SHIPPING,
                Car::STATUS_ARRIVED,
                Car::STATUS_DELIVERED,
                Car::STATUS_SOLD,
            ])],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'arrival_date.after_or_equal' => 'تاريخ الوصول يجب أن يكون بعد أو يساوي تاريخ الشحن',
            'delivery_date.after_or_equal' => 'تاريخ التسليم يجب أن يكون بعد أو يساوي تاريخ الوصول',
        ];
    }
}
