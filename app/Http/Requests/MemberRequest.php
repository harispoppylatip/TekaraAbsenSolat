<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $member = $this->route('member');
        $id = $member instanceof User ? $member->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'identifier_number' => ['required', 'string', 'max:40', Rule::unique('users', 'identifier_number')->ignore($id)],
            'email' => ['nullable', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', 'string', Rule::in(array_keys(User::roles()))],
            'class_name' => ['nullable', 'string', 'max:60', 'required_if:role,'.User::RoleMurid],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'can_scan_face' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama anggota wajib diisi.',
            'identifier_number.required' => 'Nomor induk wajib diisi, karena nomor ini dipakai alat dan absen wajah.',
            'identifier_number.unique' => 'Nomor induk itu sudah dipakai anggota lain.',
            'email.email' => 'Format email tidak benar.',
            'email.unique' => 'Email itu sudah dipakai anggota lain.',
            'role.required' => 'Peran wajib dipilih.',
            'role.in' => 'Peran yang dipilih tidak dikenal.',
            'class_name.required_if' => 'Kelas wajib diisi untuk anggota berperan murid.',
            'password.min' => 'Sandi minimal 6 karakter.',
            'photo.image' => 'Berkas wajah harus berupa gambar.',
            'photo.max' => 'Ukuran foto wajah paling besar 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->input('email')) ? trim((string) $this->input('email')) : null,
            'can_scan_face' => $this->boolean('can_scan_face'),
        ]);
    }
}
