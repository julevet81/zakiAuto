<?php

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Agent::class);
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
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            // Linking an existing user account to this agent is optional
            // and admin-only (agents/customers themselves never reach
            // this endpoint — only admin/super-admin hold agents.create).
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('agents', 'user_id')],
        ];
    }
}
