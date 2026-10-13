<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Aturan kiriman foto pada halaman pendaftaran wajah mandiri.
 *
 * Tidak ada satu pun aturan di sini yang menerima identitas dari peramban.
 * Pemilik foto selalu diambil dari akun yang sedang masuk, bukan dari isian
 * tersembunyi, supaya tidak ada cara mendaftarkan wajah untuk akun orang lain
 * walaupun alamat permintaannya ditebak.
 */
class FaceEnrollmentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Seluruh peran yang belum terdaftar wajahnya boleh mendaftar, termasuk
        // guru dan petugas, sebab mereka juga absen di depan kamera.
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $perSubmit = max(1, (int) config('face.enrollment.max_photos_per_submit', 3));

        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.$perSubmit],
            'photos.*' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'replace' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $perSubmit = max(1, (int) config('face.enrollment.max_photos_per_submit', 3));

        return [
            'photos.required' => 'Pilih atau ambil foto wajah lebih dulu.',
            'photos.min' => 'Pilih minimal satu foto wajah.',
            'photos.max' => 'Sekali kirim paling banyak '.$perSubmit.' foto. Kirim sisanya setelah ini.',
            'photos.*.image' => 'Berkas yang dipilih harus berupa gambar.',
            'photos.*.mimes' => 'Format foto harus JPEG, PNG, atau WEBP.',
            'photos.*.max' => 'Ukuran satu foto paling besar 5 MB.',
        ];
    }

    /**
     * Periksa jatah foto SEBELUM berkasnya disimpan.
     *
     * Pemeriksaan ini dikerjakan di sini, bukan di pengendali, supaya
     * permintaannya batal tanpa satu pun berkas berpindah ke penyimpanan. Foto
     * yang terlanjur disimpan padahal jatahnya penuh hanya akan jadi berkas
     * yatim yang tidak pernah diolah.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if (! $user instanceof User) {
                return;
            }

            $max = (int) config('face.enrollment.max_photos', 10);
            $max = max(1, min($max, (int) config('face.max_photos_per_user', 20)));
            $sudah = $user->faceEmbeddings()->count();
            $diminta = count((array) $this->file('photos', []));

            if ($sudah + $diminta > $max) {
                $validator->errors()->add(
                    'photos',
                    'Foto wajah Anda sudah '.$sudah.' dari batas '.$max.'. '
                    .'Paling banyak '.max(0, $max - $sudah).' foto lagi yang bisa ditambahkan. '
                    .'Hapus foto yang lama bila ingin menggantinya.',
                );
            }
        });
    }
}
