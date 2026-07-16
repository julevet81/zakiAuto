<?php

namespace App\Http\Requests\Batch;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Batch::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id'         => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_date'       => ['nullable', 'date'],

            // total_cost_foreign is NOT accepted from user input — it is
            // always derived automatically from the batch's cars
            // (see Batch::recomputeTotalCostForeign()). A batch created
            // here has no cars yet, so it starts at 0 until cars are
            // added/imported.

            // exchange_rate is NOT accepted from user input — it is always
            // derived automatically from supplier payments by the model.
            // Any submitted value is silently ignored (not in $fillable).

            'status'  => ['nullable', Rule::in([
                Batch::STATUS_PARTIAL,
                Batch::STATUS_FULLY_PAID,
            ])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
