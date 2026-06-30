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
     * This is the ONLY place staff accounts (admin, agent) get created —
     * public /auth/register always creates a "customer" (see
     * AuthController::register). The role is required and restricted to
     * the four known roles; assigning "super-admin" itself is further
     * gated to only super-admins in UserController::store(), since the
     * `roles.assign` permission alone isn't fine-grained enough to stop
     * an admin from promoting someone to super-admin.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['super-admin', 'admin', 'agent', 'customer'])],
            'is_active' => ['boolean'],

            // Optional: when role=agent, immediately create the linked
            // Agent profile row too, so the new staff member shows up in
            // the Agents module right away instead of needing a second step.
            'agent' => ['required_if:role,agent', 'array'],
            'agent.name' => ['required_if:role,agent', 'string', 'max:150'],
            'agent.phone' => ['nullable', 'string', 'max:30'],
            'agent.address' => ['nullable', 'string'],
        ];
    }
}
