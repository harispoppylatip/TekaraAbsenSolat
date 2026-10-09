<?php

namespace App\Http\Requests;

use App\Models\PrayerTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrayerTimeRequest extends FormRequest
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
        $prayer = $this->route('prayerTime');
        $id = $prayer instanceof PrayerTime ? $prayer->getKey() : null;

        return [
            'prayer_name' => ['required', 'string', 'max:30', Rule::unique('prayer_times', 'prayer_name')->ignore($id)],
            'label' => ['required', 'string', 'max:40'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'late_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prayer_name.required' => 'Kode waktu solat wajib diisi.',
            'prayer_name.unique' => 'Kode waktu solat itu sudah dipakai.',
            'label.required' => 'Nama waktu solat wajib diisi.',
            'start_time.required' => 'Jam mulai wajib diisi.',
            'start_time.date_format' => 'Jam mulai ditulis dalam bentuk 06:30.',
            'end_time.required' => 'Jam selesai wajib diisi.',
            'end_time.date_format' => 'Jam selesai ditulis dalam bentuk 07:15.',
            'end_time.after' => 'Jam selesai harus lebih akhir dari jam mulai.',
            'late_minutes.required' => 'Toleransi keterlambatan wajib diisi.',
            'late_minutes.integer' => 'Toleransi keterlambatan ditulis dalam satuan menit.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
