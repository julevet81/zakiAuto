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

            // Total agreed purchase cost in foreign currency — required for
            // exchange_rate to be calculable. May be supplied later via
            // PUT/PATCH if not yet known at creation time, but the batch's
            // exchange_rate will remain NULL until it is provided.
            'total_cost_foreign'  => ['nullable', 'numeric', 'min:0'],

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
