<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(6)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Sandi saat ini wajib diisi.',
            'password.required' => 'Sandi baru wajib diisi.',
            'password.confirmed' => 'Ulangi sandi baru dengan isi yang sama.',
            'password.min' => 'Sandi baru minimal 6 karakter.',
        ];
    }
}
