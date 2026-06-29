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
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'passport_no' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],

            // Only an admin/super-admin may explicitly choose which agent
            // a customer belongs to, or link an existing user account.
            // An agent creating a customer is auto-linked to themself
            // (see CustomerController::store) — they cannot assign a
            // customer to a different agent or to themselves explicitly.
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
                Rule::unique('customers', 'user_id'),
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
