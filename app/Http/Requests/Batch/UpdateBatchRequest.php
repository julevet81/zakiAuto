<?php

namespace App\Http\Requests\Batch;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('batch'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['sometimes', 'required', 'integer', 'exists:suppliers,id'],
            'batch_number' => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('batches', 'batch_number')->ignore($this->route('batch')),
            ],
            'purchase_date' => ['nullable', 'date'],
            'total_paid_amount_foreign' => ['nullable', 'numeric', 'min:0'],
            'exchange_rate' => ['sometimes', 'required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in([
                Batch::STATUS_PENDING,
                Batch::STATUS_PARTIAL,
                Batch::STATUS_FULLY_PAID,
                Batch::STATUS_COST_ALLOCATED,
            ])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
