<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isAdmin = $this->user()->can('customers.view');

        $rules = [
            'name'        => ['required', 'string', 'max:150'],
            'phone'       => ['nullable', 'string', 'max:30'],
            'email'       => [
                'nullable',
                'email',
                'max:150',
                Rule::unique('customers', 'email')
            ],
            'national_id' => ['nullable', 'string', 'max:50'],
            'passport_no' => ['nullable', 'string', 'max:50'],
            'address'     => ['nullable', 'string'],
        ];

        // agent_id is only validated when sent by admin/super-admin.
        // An agent creating a customer is auto-linked to themselves in
        // CustomerController::store() — they must NOT send agent_id at all.
        // If they do send it, it is silently ignored (not in rules = not
        // in validated() = never reaches the controller).
        if ($isAdmin) {
            $rules['agent_id'] = ['nullable', 'integer', 'exists:agents,id'];
        }

        return $rules;
    }
}
