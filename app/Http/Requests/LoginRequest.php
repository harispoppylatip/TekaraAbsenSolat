<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'login' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:login', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.required_without' => 'NISN atau email wajib diisi.',
            'email.required_without' => 'NISN atau email wajib diisi.',
            'password.required' => 'Sandi wajib diisi.',
        ];
    }

    public function loginCredential(): string
    {
        return trim((string) ($this->input('login') ?: $this->input('email') ?: ''));
    }
}
