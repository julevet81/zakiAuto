<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['super-admin', 'admin', 'agent'])],
            'is_active' => ['boolean'],
            'agent' => ['required_if:role,agent', 'array'],
            'agent.name' => ['required_if:role,agent', 'string', 'max:150'],
            'agent.phone' => ['nullable', 'string', 'max:30'],
            'agent.address' => ['nullable', 'string'],
        ];
    }
}
