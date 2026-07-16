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
            'purchase_date' => ['nullable', 'date'],

            // total_cost_foreign is NOT accepted from user input — it is
            // always derived automatically from the batch's cars
            // (see Batch::recomputeTotalCostForeign()), which in turn
            // feeds Batch::recomputeExchangeRate(). It changes only as a
            // side effect of adding/editing/removing a car on this batch.

            // exchange_rate is computed, never user-editable.

            'status' => ['nullable', Rule::in([
                Batch::STATUS_PARTIAL,
                Batch::STATUS_FULLY_PAID,
            ])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
