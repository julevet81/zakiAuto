<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Accept the common frontend field names while keeping the canonical API
     * payload as current_password/password/password_confirmation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'current_password' => $this->input('current_password')
                ?? $this->input('currentPassword')
                ?? $this->input('old_password')
                ?? $this->input('oldPassword'),
            'password' => $this->input('password')
                ?? $this->input('new_password')
                ?? $this->input('newPassword'),
            'password_confirmation' => $this->input('password_confirmation')
                ?? $this->input('passwordConfirmation')
                ?? $this->input('new_password_confirmation')
                ?? $this->input('newPasswordConfirmation')
                ?? $this->input('confirm_password')
                ?? $this->input('confirmPassword'),
        ]);
    }

    /**
     * Only authenticated users can change their password.
     */
    public function authorize(): bool
    {
        // Use the request's user instance to avoid undefined helper issues in static analysis
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
