<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GuardsDevice;
use App\Http\Controllers\Controller;
use App\Models\FaceEmbedding;
use App\Models\User;
use App\Services\DeviceRegistryService;
use App\Services\FaceEnrollmentService;
use App\Support\SensorReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaceApiController extends Controller
{
    use GuardsDevice;

    /**
     * Pendaftaran wajah dari alat yang sudah terpasang kamera.
     */
    public function register(
        Request $request,
        DeviceRegistryService $devices,
        FaceEnrollmentService $faces,
    ): JsonResponse {
        [$device, $refusal] = $this->guardDevice($request, $devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $data = $request->validate([
            'identifier_number' => ['required', 'string', 'max:40'],
            'photo' => ['required_without:encoding', 'file', 'image', 'max:5120'],
            'encoding' => ['required_without:photo', 'array', 'size:'.FaceEmbedding::Dimensions],
            'encoding.*' => ['numeric'],
            'replace' => ['nullable', 'boolean'],
        ], [
            'identifier_number.required' => 'Nomor induk wajib dikirim alat.',
            'photo.required_without' => 'Foto wajah tidak diterima.',
            'encoding.required_without' => 'Angka wajah tidak diterima.',
            'encoding.size' => 'Angka wajah harus berjumlah '.FaceEmbedding::Dimensions.'.',
            'encoding.*.numeric' => 'Angka wajah harus berupa angka.',
        ]);

        $member = User::query()->where('identifier_number', $data['identifier_number'])->first();

        if ($member === null) {
            $reply = SensorReply::refusal('unknown_member', 'Nomor tak dikenal', 422);

            return response()->json($reply['payload'], $reply['http']);
        }

        $label = 'Kamera '.($device?->name ?? 'gerbang');
        $replace = $request->boolean('replace');

        if ($request->hasFile('photo')) {
            // Wajah lama TIDAK dihapus lebih dulu. Penggantian dikerjakan di
            // dalam penyimpanan angkanya, sesudah angka wajah yang baru berhasil
            // dibaca dan tersimpan; jadi foto yang gagal tidak akan mengubah
            // apa pun, dan anggota tidak pernah kehilangan wajahnya karena satu
            // kiriman alat yang gagal.
            $result = $faces->enrollFromUpload($member, $request->file('photo'), $label, $replace);

            if ($result['status'] !== FaceEnrollmentService::StatusSaved) {
                $reply = SensorReply::refusal($result['status'], 'Wajah tak jelas', 422);

                return response()->json($reply['payload'], $reply['http']);
            }
        } else {
            $stored = $faces->store($member, $data['encoding'], $label, $replace);
            $result = [
                'status' => FaceEnrollmentService::StatusSaved,
                'message' => 'Satu wajah '.$member->name.' tersimpan.',
                'total' => $faces->photoCount($member),
                'embedding_id' => $stored->id,
            ];
        }

        return response()->json([
            'ok' => true,
            'blocked' => false,
            'status' => 'saved',
            'message' => $result['message'],
            'display_message' => 'Wajah tersimpan',
            'display_detail' => $member->name,
            'member' => [
                'name' => $member->name,
                'identifier_number' => $member->identifier_number,
            ],
            'total' => $result['total'],
        ], 201);
    }

    /**
     * Daftar angka wajah untuk alat yang mencocokkan sendiri di dalam alat.
     */
    public function embeddings(Request $request, DeviceRegistryService $devices): JsonResponse
    {
        [, $refusal] = $this->guardDevice($request, $devices);

        if ($refusal !== null) {
            return $refusal;
        }

        $rows = FaceEmbedding::query()
            ->with('user:id,name,identifier_number,class_name,enrollment_status')
            ->whereHas('user', fn ($query) => $query->where('enrollment_status', User::EnrollmentCompleted))
            ->orderBy('user_id')
            ->get()
            ->filter(fn (FaceEmbedding $row) => $row->user !== null && $row->user->hasCompletedEnrollment())
            ->map(fn (FaceEmbedding $row): array => [
                'user_id' => $row->user_id,
                'name' => $row->user->name,
                'identifier_number' => $row->user->identifier_number,
                'class_name' => $row->user->class_name,
                'model' => $row->model,
                'embedding' => $row->embedding,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'blocked' => false,
            'status' => 'ok',
            'message' => $rows->count().' wajah siap dipakai alat.',
            'display_message' => 'Daftar wajah',
            'display_detail' => $rows->count().' wajah',
            'tolerance' => (float) config('face.tolerance', 0.5),
            'single_photo_tolerance' => (float) config('face.single_photo_tolerance', 0.45),
            'dimensions' => FaceEmbedding::Dimensions,
            'total' => $rows->count(),
            'embeddings' => $rows,
        ]);
    }
}
