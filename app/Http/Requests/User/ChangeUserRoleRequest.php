<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChangeUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('roles.assign');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['super-admin', 'admin', 'agent', 'customer'])],
        ];
    }

    /**
     * Only an existing super-admin may grant or revoke the super-admin
     * role itself — an admin holding roles.assign (which, per the
     * seeder, admins never actually get since it's under the `roles.*`
     * prefix excluded from their permission set) still could not use
     * this to self-promote. This check is a defense-in-depth backstop
     * in case the permission model changes later.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $targetRole = $this->input('role');
            $actingUser = $this->user();

            if ($targetRole === 'super-admin' && ! $actingUser->hasRole('super-admin')) {
                $validator->errors()->add('role', 'فقط مدير النظام (Super Admin) يمكنه منح أو سحب هذا الدور');
            }
        });
    }
}
