<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('customer'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'passport_no' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],

            // Re-assigning a customer to a different agent, or linking a
            // user account, stays an admin-only action — same reasoning
            // as StoreCustomerRequest.
            'agent_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
                Rule::prohibitIf(! $this->user()->can('customers.view')),
            ],
            'user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::unique('customers', 'user_id')->ignore($this->route('customer')),
                Rule::prohibitIf(! $this->user()->can('customers.view')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agent_id.prohibited' => 'لا تملك صلاحية تحديد الوكيل مباشرة',
            'user_id.prohibited' => 'لا تملك صلاحية ربط حساب مستخدم مباشرة',
        ];
    }
}
