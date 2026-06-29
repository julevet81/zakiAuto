<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('order'));
    }

    /**
     * Status is intentionally NOT editable through this endpoint — see
     * UpdateOrderStatusRequest / OrderController::changeStatus(), which
     * enforces the strict forward-only workflow. Allowing status here too
     * would let it bypass that validation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'purchase_date' => ['nullable', 'date'],
            'shipping_date' => ['nullable', 'date'],
            'arrival_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
