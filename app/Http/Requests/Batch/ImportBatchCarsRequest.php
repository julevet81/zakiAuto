<?php

namespace App\Http\Requests\Batch;

use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;

class ImportBatchCarsRequest extends FormRequest
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
            // Entered manually on the same screen as the file upload,
            // before/while importing — per the agreed flow.
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'container_opener_id' => ['nullable', 'integer', 'exists:container_openers,id'],
            'purchase_date' => ['nullable', 'date'],
            'total_cost_foreign' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],

            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'يجب أن يكون الملف بصيغة xlsx أو xls أو csv',
            'file.max' => 'حجم الملف يجب ألا يتجاوز 10 ميجابايت',
        ];
    }
}
