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
            'name'        => ['required', 'string', 'max:150'],

            // email is required when creating via the staff panel because
            // the system will auto-create a User account and send login
            // credentials to this address. Must be globally unique across
            // the users table (not just customers) since a User row will
            // be created with this email.
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email'),
                Rule::unique('customers', 'email'),
            ],

            'phone'       => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'passport_no' => ['nullable', 'string', 'max:50'],
            'address'     => ['nullable', 'string'],

            // Only admin/super-admin may assign a customer to an agent.
            // An agent creating a customer is auto-linked to themselves
            // (see CustomerController::store).
            'agent_id' => [
                'nullable',
                'integer',
                'exists:agents,id',
                Rule::prohibitIf(! $this->user()->can('customers.view')),
            ],

            // user_id is NO LONGER accepted from input — the User account
            // is always created automatically by CustomerController::store()
            // using the provided email + a random password.
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required'       => 'البريد الإلكتروني مطلوب لإنشاء حساب العميل',
            'email.unique'         => 'هذا البريد الإلكتروني مستخدم بالفعل',
            'agent_id.prohibited'  => 'لا تملك صلاحية تحديد الوكيل مباشرة',
        ];
    }
}
