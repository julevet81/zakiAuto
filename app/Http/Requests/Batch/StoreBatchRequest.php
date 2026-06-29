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
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'batch_number' => ['required', 'string', 'max:30', Rule::unique('batches', 'batch_number')],
            'purchase_date' => ['nullable', 'date'],
            'total_paid_amount_foreign' => ['nullable', 'numeric', 'min:0'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
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
