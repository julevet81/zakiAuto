<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * Role re-assignment and password resets go through their own
     * dedicated endpoints (UserController::changeRole / resetPassword)
     * rather than this general field-edit request, for the same reason
     * Order's status has its own endpoint: mixing a sensitive,
     * audit-worthy action into a generic PATCH makes it too easy to
     * change accidentally as a side effect of an unrelated edit.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => [
                'sometimes', 'required', 'string', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
