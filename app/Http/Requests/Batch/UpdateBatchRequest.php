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
            'supplier_id'   => ['sometimes', 'required', 'integer', 'exists:suppliers,id'],
            'batch_number'  => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('batches', 'batch_number')->ignore($this->route('batch')),
            ],
            'purchase_date' => ['nullable', 'date'],

            // Updating total_cost_foreign triggers an automatic
            // recomputeExchangeRate() call in BatchController::update()
            // since the denominator of the formula has changed.
            'total_cost_foreign' => ['nullable', 'numeric', 'min:0'],

            // exchange_rate is computed, never user-editable.

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
