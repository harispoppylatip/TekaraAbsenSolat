<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\FaceEmbedding;
use App\Models\Fingerprint;
use App\Models\User;
use App\Services\FaceEnrollmentService;
use App\Services\SolatProofService;
use App\Services\SystemBackupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MemberController extends Controller
{
    /** Jumlah anggota per halaman pada daftar. */
    public const PerPage = 15;

    /** Pilihan pengurutan yang dikenal. */
    public const Sorts = ['name', 'identifier', 'role', 'recent'];

    /** Daftar kode jurusan umum SMK sebagai saran dan pencocokan otomatis. */
    public const DefaultMajors = [
        'TKJ',
        'TKRO',
        'TBSM',
        'AKL',
        'MPLB',
        'DKV',
        'RPL',
        'TITL',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->buildFilteredQuery($filters)
            ->withCount([
                'faceEmbeddings',
                'attendanceLogs' => fn (Builder $inner) => $inner->whereHas('session', fn (Builder $session) => $session->real()),
            ]);

        return view('members.index', [
            'members' => $query->paginate(self::PerPage)->withQueryString(),
            'filters' => $filters,
            'classes' => $this->classOptions($filters['class_name']),
            'majors' => $this->majorOptions($filters['major']),
            'stats' => [
                'total' => User::query()->count(),
                'murid' => User::query()->where('role', User::RoleMurid)->count(),
                'petugas' => User::query()->whereIn('role', [User::RolePetugas, User::RoleAdmin])->count(),
                'berwajah' => User::query()->has('faceEmbeddings')->count(),
            ],
        ]);
    }

    /**
     * Ekspor seluruh data anggota (sesuai saringan aktif bila ada) ke dalam satu paket .zip
     * yang berisi tabel CSV/Excel, data lengkap JSON (termasuk embedding AI & sidik jari),
     * serta seluruh berkas foto wajah anggota.
     */
    public function export(Request $request, SystemBackupService $backup): Response
    {
        $filters = $this->filters($request);
        $members = $this->buildFilteredQuery($filters)
            ->with(['faceEmbeddings', 'fingerprints'])
            ->withCount([
                'faceEmbeddings',
                'fingerprints',
                'attendanceLogs' => fn (Builder $inner) => $inner->whereHas('session', fn (Builder $session) => $session->real()),
            ])
            ->get();

        $entries = [];
        $disk = Storage::disk('local');

        $csvExcelLines = fopen('php://temp', 'r+');
        $csvCommaLines = fopen('php://temp', 'r+');
        fwrite($csvExcelLines, "\xEF\xBB\xBF");
        fwrite($csvCommaLines, "\xEF\xBB\xBF");

        $headers = [
            'nama',
            'nisn',
            'email',
            'peran',
            'kelas',
            'jurusan',
            'telepon',
            'status_wajah',
            'jumlah_foto_wajah',
            'jumlah_sidik_jari',
            'total_hadir',
        ];
        fputcsv($csvExcelLines, $headers, ';');
        fputcsv($csvCommaLines, $headers, ',');

        $membersPayload = [];
        $totalPhotosExported = 0;

        foreach ($members as $member) {
            if ($member->isExemptFromEnrollment()) {
                $faceStatusLabel = $member->face_embeddings_count > 0
                    ? 'Tidak Wajib Wajah (Terdaftar)'
                    : 'Tidak Wajib Wajah';
            } elseif ($member->face_embeddings_count > 0) {
                $faceStatusLabel = 'Sudah Terdaftar';
            } else {
                $faceStatusLabel = 'Belum Terdaftar';
            }

            $row = [
                $member->name,
                $member->identifier_number,
                $member->email ?? '',
                $member->role,
                $member->class_name ?? '',
                $member->major ?? '',
                $member->phone_number ?? '',
                $faceStatusLabel,
                (string) $member->face_embeddings_count,
                (string) $member->fingerprints_count,
                (string) $member->attendance_logs_count,
            ];

            fputcsv($csvExcelLines, $row, ';');
            fputcsv($csvCommaLines, $row, ',');

            $safeIdentifier = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $member->identifier_number) ?: ('id_'.$member->id);
            $safeName = Str::slug($member->name ?: 'anggota', '_');
            $memberFolder = 'foto-anggota/'.$safeIdentifier.'_'.$safeName;

            $embeddingsPayload = [];
            foreach ($member->faceEmbeddings as $index => $faceEmbedding) {
                $photoRelPath = $faceEmbedding->photo_path ? str_replace('\\', '/', $faceEmbedding->photo_path) : null;
                $hasPhoto = $photoRelPath && $disk->exists($photoRelPath);

                if ($hasPhoto) {
                    $photoBytes = $disk->get($photoRelPath);
                    $entries['storage/'.ltrim($photoRelPath, '/')] = $photoBytes;

                    $ext = pathinfo($photoRelPath, PATHINFO_EXTENSION) ?: 'jpg';
                    $poseSlug = Str::slug($faceEmbedding->label ?: ('foto_'.($index + 1)), '_');
                    $humanFilename = sprintf('%02d_%s.%s', $index + 1, $poseSlug, $ext);
                    $entries[$memberFolder.'/'.$humanFilename] = $photoBytes;
                    $totalPhotosExported++;
                }

                $embeddingsPayload[] = [
                    'label' => $faceEmbedding->label,
                    'photo_path' => $photoRelPath,
                    'model' => $faceEmbedding->model,
                    'embedding' => $faceEmbedding->embedding,
                ];
            }

            $fingerprintsPayload = [];
            foreach ($member->fingerprints as $fingerprint) {
                $fingerprintsPayload[] = [
                    'template' => $fingerprint->template,
                    'finger_position' => $fingerprint->finger_position,
                ];
            }

            $membersPayload[] = [
                'name' => $member->name,
                'identifier_number' => $member->identifier_number,
                'email' => $member->email,
                'role' => $member->role,
                'class_name' => $member->class_name,
                'major' => $member->major,
                'phone_number' => $member->phone_number,
                'can_scan_face' => (bool) $member->can_scan_face,
                'enrollment_status' => $member->enrollment_status,
                'enrollment_started_at' => $member->enrollment_started_at?->toIso8601String(),
                'enrolled_at' => $member->enrolled_at?->toIso8601String(),
                'password' => $member->getAuthPassword(),
                'password_changed_at' => $member->password_changed_at
                    ? \Illuminate\Support\Carbon::parse($member->password_changed_at)->toIso8601String()
                    : null,
                'face_embeddings' => $embeddingsPayload,
                'fingerprints' => $fingerprintsPayload,
            ];
        }

        rewind($csvExcelLines);
        $entries['data-anggota-excel.csv'] = (string) stream_get_contents($csvExcelLines);
        fclose($csvExcelLines);

        rewind($csvCommaLines);
        $entries['data-anggota.csv'] = (string) stream_get_contents($csvCommaLines);
        fclose($csvCommaLines);

        $entries['members-data.json'] = json_encode([
            'format' => 'tekara-absen-solat-members-export-v1',
            'exported_at' => now()->toIso8601String(),
            'members_count' => count($membersPayload),
            'photos_count' => $totalPhotosExported,
            'members' => $membersPayload,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $zipBytes = $backup->buildZip($entries);
        $filename = 'data-anggota-lengkap-'.now()->format('Y-m-d-His').'.zip';

        return response($zipBytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($zipBytes),
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function importForm(): View
    {
        return view('members.import');
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $delimiter = $request->query('delimiter') === 'comma' ? ',' : ';';
        $filename = $delimiter === ',' ? 'template-impor-anggota.csv' : 'template-impor-anggota-excel.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($delimiter) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['nama', 'nisn', 'email', 'peran', 'kelas', 'jurusan'], $delimiter);
            fputcsv($handle, ['Ahmad Fauzi', '25001', '', 'murid', 'XII', 'TKJ'], $delimiter);
            fputcsv($handle, ['Siti Nurhaliza', '25002', 'siti@contoh.sch.id', 'murid', 'X .21', 'TKJ'], $delimiter);
            fputcsv($handle, ['Bambang Irawan', 'GUR-001', 'bambang@contoh.sch.id', 'guru', '', ''], $delimiter);
            fputcsv($handle, ['Ratna Sari', 'ADM-002', 'ratna@contoh.sch.id', 'admin', '', ''], $delimiter);

            fclose($handle);
        }, 200, $headers);
    }

    public function import(Request $request, SystemBackupService $backup): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,zip', 'max:102400'],
            'update_existing' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'Pilih berkas CSV atau paket ZIP terlebih dahulu.',
            'file.mimes' => 'Berkas harus berformat CSV (.csv) atau paket ekspor anggota (.zip).',
            'file.max' => 'Ukuran berkas maksimal 100 MB.',
        ]);

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->getRealPath();
        $extension = strtolower((string) $uploadedFile->getClientOriginalExtension());

        if ($extension === 'zip' || $uploadedFile->getMimeType() === 'application/zip') {
            return $this->importFromZip((string) file_get_contents($path), $request->boolean('update_existing', true), $backup);
        }

        $handle = fopen($path, 'r');
        if (! $handle) {
            return back()->with('error', 'Gagal membuka berkas CSV yang diunggah.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false || trim($firstLine) === '') {
            fclose($handle);

            return back()->with('error', 'Berkas CSV kosong.');
        }

        $cleanFirstLine = trim(preg_replace('/[\x{FEFF}]/u', '', $firstLine));
        $delimiter = ',';

        if (str_starts_with(mb_strtolower($cleanFirstLine), 'sep=')) {
            $sepChar = substr($cleanFirstLine, 4, 1);
            if (in_array($sepChar, [',', ';', "\t", '|'], true)) {
                $delimiter = $sepChar;
            }
        } else {
            $semicolons = substr_count($firstLine, ';');
            $commas = substr_count($firstLine, ',');
            $tabs = substr_count($firstLine, "\t");
            if ($semicolons > $commas && $semicolons > $tabs) {
                $delimiter = ';';
            } elseif ($tabs > $commas && $tabs > $semicolons) {
                $delimiter = "\t";
            }
            rewind($handle);
        }

        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (! $rawHeaders) {
            fclose($handle);

            return back()->with('error', 'Header kolom CSV tidak dapat dibaca.');
        }

        $headerMap = [];
        foreach ($rawHeaders as $idx => $h) {
            $normalized = mb_strtolower(trim(preg_replace('/[\x{FEFF}]/u', '', (string) $h)));
            if (in_array($normalized, ['nama', 'name', 'nama lengkap', 'nama_lengkap'], true)) {
                $headerMap['name'] = $idx;
            } elseif (in_array($normalized, ['nisn', 'nomor induk', 'nomor_induk', 'identifier', 'identifier_number', 'no_induk', 'nip', 'nik'], true)) {
                $headerMap['identifier_number'] = $idx;
            } elseif (in_array($normalized, ['email', 'surel', 'emailopsional', 'email_opsional', 'email opsional'], true)) {
                $headerMap['email'] = $idx;
            } elseif (in_array($normalized, ['peran', 'role'], true)) {
                $headerMap['role'] = $idx;
            } elseif (in_array($normalized, ['kelas', 'class', 'class_name', 'tingkat'], true)) {
                $headerMap['class_name'] = $idx;
            } elseif (in_array($normalized, ['jurusan', 'major', 'kompetensi', 'kompetensi keahlian', 'kompetensi_keahlian', 'program keahlian', 'program_keahlian', 'prodi'], true)) {
                $headerMap['major'] = $idx;
            }
        }

        if (! isset($headerMap['name']) || ! isset($headerMap['identifier_number'])) {
            fclose($handle);

            return back()->with('error', 'Format CSV tidak sesuai. Berkas harus memiliki kolom "nama" dan "nisn" (atau nomor induk).');
        }

        $updateExisting = $request->boolean('update_existing', true);
        $created = 0;
        $updated = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($row === [null] || empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                continue;
            }

            $name = trim((string) ($row[$headerMap['name']] ?? ''));
            $identifier = trim((string) ($row[$headerMap['identifier_number']] ?? ''));
            $rawEmail = isset($headerMap['email']) ? trim((string) ($row[$headerMap['email']] ?? '')) : '';
            $rawRole = isset($headerMap['role']) ? mb_strtolower(trim((string) ($row[$headerMap['role']] ?? ''))) : '';
            $rawClass = isset($headerMap['class_name']) ? trim((string) ($row[$headerMap['class_name']] ?? '')) : '';
            $rawMajor = isset($headerMap['major']) ? trim((string) ($row[$headerMap['major']] ?? '')) : '';

            if ($name === '') {
                $errors[] = "Baris {$rowNumber}: Kolom nama kosong.";

                continue;
            }

            if ($identifier === '') {
                $errors[] = "Baris {$rowNumber}: Kolom NISN / nomor induk kosong.";

                continue;
            }

            $email = null;
            if ($rawEmail !== '') {
                if (! filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Baris {$rowNumber}: Format email ({$rawEmail}) tidak sah.";

                    continue;
                }
                $email = $rawEmail;
            }

            $role = User::RoleMurid;
            if (in_array($rawRole, ['admin', 'administrator', 'operator'], true)) {
                $role = User::RoleAdmin;
            } elseif (in_array($rawRole, ['guru', 'petugas', 'teacher', 'staff', 'pengajar', 'ustadz', 'ustadzah'], true)) {
                $role = User::RolePetugas;
            } elseif (in_array($rawRole, ['murid', 'murdi', 'siswa', 'santri', 'pelajar', 'student'], true)) {
                $role = User::RoleMurid;
            }

            $className = $role === User::RoleMurid ? ($rawClass !== '' ? $rawClass : null) : null;
            $major = $role === User::RoleMurid
                ? ($rawMajor !== '' ? $rawMajor : $this->extractMajorFromClassName($className))
                : null;

            $existing = User::where('identifier_number', $identifier)->first();

            if ($email !== null) {
                $conflict = User::where('email', $email)
                    ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                    ->first();

                if ($conflict) {
                    $errors[] = "Baris {$rowNumber}: Email {$email} sudah digunakan oleh anggota lain ({$conflict->name}).";

                    continue;
                }
            }

            if ($existing) {
                if ($updateExisting) {
                    $existing->name = $name;
                    if ($email !== null) {
                        $existing->email = $email;
                    }
                    $existing->role = $role;
                    if ($role === User::RoleMurid) {
                        if ($className !== null) {
                            $existing->class_name = $className;
                        }
                        if ($major !== null) {
                            $existing->major = $major;
                        }
                    } else {
                        $existing->class_name = null;
                        $existing->major = null;
                        if (in_array($role, [User::RolePetugas, User::RoleAdmin], true)) {
                            $existing->can_scan_face = true;
                        }
                    }
                    $existing->save();
                    $updated++;
                }
            } else {
                User::create([
                    'name' => $name,
                    'identifier_number' => $identifier,
                    'email' => $email,
                    'role' => $role,
                    'class_name' => $className,
                    'major' => $major,
                    'can_scan_face' => in_array($role, [User::RolePetugas, User::RoleAdmin], true),
                    'password' => User::defaultPasswordFor($role, $identifier),
                ]);
                $created++;
            }
        }

        fclose($handle);

        $totalProcessed = $created + $updated;

        if ($totalProcessed === 0 && $errors !== []) {
            $message = 'Tidak ada anggota yang diimpor. '.implode(' ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $message .= ' dan '.(count($errors) - 3).' baris lainnya dilewati.';
            }

            return back()->with('error', $message);
        }

        $notice = "Impor selesai: {$totalProcessed} anggota berhasil diproses ({$created} baru, {$updated} diperbarui).";
        $redirect = redirect()->route('members.index')->with('status', $notice);

        if ($errors !== []) {
            $warn = 'Beberapa baris dilewati: '.implode(' ', array_slice($errors, 0, 3));
            if (count($errors) > 3) {
                $warn .= ' dan '.(count($errors) - 3).' baris lainnya.';
            }
            $redirect->with('warning', $warn);
        }

        return $redirect;
    }

    public function create(): View
    {
        return view('members.form', [
            'member' => new User(['role' => User::RoleMurid, 'can_scan_face' => false]),
            'classes' => $this->classOptions(),
            'majors' => $this->majorOptions(),
            'mode' => 'create',
        ]);
    }

    public function store(MemberRequest $request, FaceEnrollmentService $faces): RedirectResponse
    {
        $data = $request->validated();

        $className = $data['class_name'] ?? null;
        $major = $data['major'] ?? ($data['role'] === User::RoleMurid ? $this->extractMajorFromClassName($className) : null);

        $canScanFace = in_array($data['role'], [User::RolePetugas, User::RoleAdmin], true)
            ? true
            : $request->boolean('can_scan_face');

        $member = User::create([
            'name' => $data['name'],
            'identifier_number' => $data['identifier_number'],
            'email' => $data['email'],
            'role' => $data['role'],
            'class_name' => $className,
            'major' => $major,
            'phone_number' => $data['phone_number'] ?? null,
            'can_scan_face' => $canScanFace,
            'enrollment_status' => $data['role'] === User::RoleKiosk ? User::EnrollmentExempted : User::EnrollmentNotStarted,
            'password' => $data['password'] ?? User::defaultPasswordFor($data['role'], $data['identifier_number']),
        ]);

        if ($member->isKiosk()) {
            $member->forceFill(['password_changed_at' => now()])->save();
        }

        $notice = 'Anggota '.$member->name.' tersimpan. Sandi awal: '
            .($data['password'] ?? User::defaultPasswordFor($data['role'], $data['identifier_number'])).'.';

        if ($request->hasFile('photo')) {
            $result = $faces->enrollFromUpload($member, $request->file('photo'), 'Foto pendaftaran');

            if ($result['status'] === FaceEnrollmentService::StatusSaved) {
                $notice .= ' '.$result['message'];
            } else {
                return redirect()
                    ->route('members.edit', $member)
                    ->with('status', $notice)
                    ->with('warning', 'Anggota tetap tersimpan, tetapi wajahnya belum terdaftar. '.$result['message']);
            }
        }

        return redirect()->route('members.edit', $member)->with('status', $notice);
    }

    public function edit(User $member): View
    {
        return view('members.form', [
            'member' => $member->loadCount('faceEmbeddings'),
            'classes' => $this->classOptions($member->class_name),
            'majors' => $this->majorOptions($member->major),
            'mode' => 'edit',
        ]);
    }

    public function update(MemberRequest $request, User $member, FaceEnrollmentService $faces): RedirectResponse
    {
        $data = $request->validated();

        $className = $data['class_name'] ?? null;
        $major = $data['major'] ?? ($data['role'] === User::RoleMurid ? $this->extractMajorFromClassName($className) : null);

        $canScanFace = in_array($data['role'], [User::RolePetugas, User::RoleAdmin], true)
            ? true
            : $request->boolean('can_scan_face');

        $member->fill([
            'name' => $data['name'],
            'identifier_number' => $data['identifier_number'],
            'email' => $data['email'],
            'role' => $data['role'],
            'class_name' => $className,
            'major' => $major,
            'phone_number' => $data['phone_number'] ?? null,
            'can_scan_face' => $canScanFace,
        ]);

        if (filled($data['password'] ?? null)) {
            $member->password = $data['password'];
            $member->password_changed_at = $data['role'] === User::RoleKiosk ? now() : null;
        }

        if ($data['role'] === User::RoleKiosk) {
            $member->enrollment_status = User::EnrollmentExempted;
            $member->password_changed_at = $member->password_changed_at ?? now();
        }

        $member->save();

        if ($request->hasFile('photo')) {
            $result = $faces->enrollFromUpload($member, $request->file('photo'), 'Foto pendaftaran');

            if ($result['status'] !== FaceEnrollmentService::StatusSaved) {
                return back()->with('status', 'Data anggota diperbarui.')->with('warning', 'Wajah belum terdaftar. '.$result['message']);
            }

            return back()->with('status', 'Data anggota diperbarui. '.$result['message']);
        }

        return back()->with('status', 'Data anggota '.$member->name.' diperbarui.');
    }

    public function resetPassword(User $member): RedirectResponse
    {
        $password = User::defaultPasswordFor((string) $member->role, $member->identifier_number);

        $member->forceFill([
            'password' => Hash::make($password),
            'password_changed_at' => null,
        ])->save();

        return back()->with('status', 'Sandi '.$member->name.' dikembalikan ke '.$password.'.');
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        if ($request->user()?->is($member)) {
            return back()->with('error', 'Akun yang sedang dipakai tidak bisa dihapus.');
        }

        $name = $member->name;

        // Catatan absennya ikut terhapus bersama anggotanya lewat kunci asing,
        // jadi foto buktinya harus dibersihkan lebih dulu supaya tidak ada
        // berkas yang tertinggal tanpa catatan.
        app(SolatProofService::class)->forgetForUser($member);

        $member->delete();

        return redirect()
            ->route('members.index')
            ->with('status', 'Anggota '.$name.' beserta data wajahnya dihapus. Riwayat absen ikut terhapus.');
    }

    /**
     * Impor paket .zip hasil ekspor data anggota lengkap (profil, foto wajah, embedding AI, dan sidik jari).
     */
    private function importFromZip(string $zipBytes, bool $updateExisting, SystemBackupService $backup): RedirectResponse
    {
        try {
            $entries = $backup->extractZip($zipBytes);
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal mengekstrak paket ZIP: '.$e->getMessage());
        }

        if (! isset($entries['members-data.json'])) {
            return back()->with('error', 'Paket ZIP bukan hasil ekspor data anggota yang sah (members-data.json tidak ditemukan).');
        }

        $decoded = json_decode((string) $entries['members-data.json'], true);
        if (! is_array($decoded) || ! isset($decoded['members']) || ! is_array($decoded['members'])) {
            return back()->with('error', 'Format members-data.json di dalam paket ZIP tidak dapat dibaca.');
        }

        $disk = Storage::disk('local');
        $created = 0;
        $updated = 0;
        $restoredPhotos = 0;
        $restoredFingerprints = 0;

        DB::beginTransaction();
        try {
            foreach ($decoded['members'] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $identifier = trim((string) ($row['identifier_number'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
                if ($identifier === '' || $name === '') {
                    continue;
                }

                $role = in_array(($row['role'] ?? ''), array_keys(User::roles()), true)
                    ? (string) $row['role']
                    : User::RoleMurid;

                $email = filled($row['email'] ?? null) ? trim((string) $row['email']) : null;
                $existing = User::where('identifier_number', $identifier)->first();

                if ($email !== null) {
                    $conflict = User::where('email', $email)
                        ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                        ->first();
                    if ($conflict) {
                        $email = null;
                    }
                }

                if ($existing && ! $updateExisting) {
                    continue;
                }

                $member = $existing ?? new User();
                $member->fill([
                    'name' => $name,
                    'identifier_number' => $identifier,
                    'email' => $email ?? $member->email,
                    'role' => $role,
                    'class_name' => $role === User::RoleMurid ? ($row['class_name'] ?? null) : null,
                    'major' => $role === User::RoleMurid ? ($row['major'] ?? null) : null,
                    'phone_number' => $row['phone_number'] ?? $member->phone_number,
                    'can_scan_face' => (bool) ($row['can_scan_face'] ?? false),
                    'enrollment_status' => $row['enrollment_status'] ?? ($role === User::RoleKiosk ? User::EnrollmentExempted : User::EnrollmentNotStarted),
                ]);

                if (! $existing) {
                    $rawPassword = (string) ($row['password'] ?? '');
                    if ($rawPassword !== '' && str_starts_with($rawPassword, '$2y$')) {
                        $member->setRawAttributes(array_merge($member->getAttributes(), [
                            'password' => $rawPassword,
                        ]));
                    } else {
                        $member->password = User::defaultPasswordFor($role, $identifier);
                    }
                }

                if (! empty($row['enrollment_started_at'])) {
                    $member->enrollment_started_at = \Illuminate\Support\Carbon::parse($row['enrollment_started_at']);
                }
                if (! empty($row['enrolled_at'])) {
                    $member->enrolled_at = \Illuminate\Support\Carbon::parse($row['enrolled_at']);
                }
                if (! empty($row['password_changed_at'])) {
                    $member->password_changed_at = \Illuminate\Support\Carbon::parse($row['password_changed_at']);
                }

                $member->save();

                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }

                if (! empty($row['face_embeddings']) && is_array($row['face_embeddings'])) {
                    $member->faceEmbeddings()->delete();

                    foreach ($row['face_embeddings'] as $embData) {
                        if (! is_array($embData) || empty($embData['embedding'])) {
                            continue;
                        }

                        $photoPath = ! empty($embData['photo_path'])
                            ? ltrim(str_replace('\\', '/', (string) $embData['photo_path']), '/')
                            : null;

                        if ($photoPath && isset($entries['storage/'.$photoPath])) {
                            $disk->put($photoPath, $entries['storage/'.$photoPath]);
                        }

                        FaceEmbedding::create([
                            'user_id' => $member->id,
                            'label' => $embData['label'] ?? 'Foto wajah',
                            'photo_path' => $photoPath,
                            'model' => $embData['model'] ?? 'insightface-buffalo_l',
                            'embedding' => $embData['embedding'],
                        ]);
                        $restoredPhotos++;
                    }
                }

                if (! empty($row['fingerprints']) && is_array($row['fingerprints'])) {
                    $member->fingerprints()->delete();

                    foreach ($row['fingerprints'] as $fpData) {
                        if (! is_array($fpData) || empty($fpData['template'])) {
                            continue;
                        }

                        Fingerprint::create([
                            'user_id' => $member->id,
                            'template' => (string) $fpData['template'],
                            'finger_position' => $fpData['finger_position'] ?? null,
                        ]);
                        $restoredFingerprints++;
                    }
                }
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Gagal memulihkan paket data anggota: '.$e->getMessage());
        }

        $total = $created + $updated;

        return redirect()
            ->route('members.index')
            ->with('status', "Impor paket ZIP selesai: {$total} anggota diproses ({$created} baru, {$updated} diperbarui), {$restoredPhotos} foto wajah AI, dan {$restoredFingerprints} sidik jari berhasil dipulihkan.");
    }

    /**
     * Bangun kueri anggota berdasarkan saringan dan urutan aktif.
     *
     * @param  array{q: string, role: string, class_name: string, major: string, face: string, sort: string}  $filters
     * @return Builder<User>
     */
    private function buildFilteredQuery(array $filters): Builder
    {
        $query = User::query()
            ->search($filters['q'] ?: null)
            ->when($filters['role'] !== '', fn (Builder $inner) => $inner->where('role', $filters['role']))
            ->when($filters['class_name'] !== '', fn (Builder $inner) => $this->applyClassFilter($inner, $filters['class_name']))
            ->when($filters['major'] !== '', fn (Builder $inner) => $this->applyMajorFilter($inner, $filters['major']))
            ->when($filters['face'] === 'ada', fn (Builder $inner) => $inner->has('faceEmbeddings'))
            ->when($filters['face'] === 'kosong', function (Builder $inner) {
                $inner->doesntHave('faceEmbeddings')
                    ->where('role', '!=', User::RoleKiosk)
                    ->where(function (Builder $sub) {
                        $sub->whereNull('enrollment_status')
                            ->orWhere('enrollment_status', '!=', User::EnrollmentExempted);
                    });
            })
            ->when($filters['face'] === 'bebas', function (Builder $inner) {
                $inner->where(function (Builder $sub) {
                    $sub->where('role', User::RoleKiosk)
                        ->orWhere('enrollment_status', User::EnrollmentExempted);
                });
            });

        match ($filters['sort']) {
            'identifier' => $query->orderBy('identifier_number'),
            'role' => $query->orderBy('role')->orderBy('name'),
            'recent' => $query->orderByDesc('last_login_at')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        return $query;
    }

    /**
     * Saringan dari alamat halaman, disaring memakai daftar putih supaya
     * kolom sembarang tidak bisa dipakai menyaring.
     *
     * @return array{q: string, role: string, class_name: string, major: string, face: string, sort: string}
     */
    private function filters(Request $request): array
    {
        $role = (string) $request->query('role', '');
        $face = (string) $request->query('face', '');
        $sort = (string) $request->query('sort', 'name');

        return [
            'q' => trim((string) $request->query('q', '')),
            'role' => in_array($role, array_keys(User::roles()), true) ? $role : '',
            'class_name' => trim((string) $request->query('class_name', '')),
            'major' => trim((string) $request->query('major', '')),
            'face' => in_array($face, ['ada', 'kosong', 'bebas'], true) ? $face : '',
            'sort' => in_array($sort, self::Sorts, true) ? $sort : 'name',
        ];
    }

    /**
     * Terapkan saringan kelas, baik berdasarkan tingkat (10/X, 11/XI, 12/XII)
     * maupun nama kelas spesifik.
     *
     * @param  Builder<User>  $query
     */
    private function applyClassFilter(Builder $query, string $classFilter): void
    {
        $upper = strtoupper(trim($classFilter));

        if (in_array($upper, ['10', 'X'], true)) {
            $query->where(function (Builder $q) {
                $q->whereIn('class_name', ['10', 'X', 'x'])
                    ->orWhere('class_name', 'like', '10 %')
                    ->orWhere('class_name', 'like', '10.%')
                    ->orWhere('class_name', 'like', '10-%')
                    ->orWhere(function (Builder $sub) {
                        $sub->where(function (Builder $x) {
                            $x->where('class_name', 'like', 'X %')
                                ->orWhere('class_name', 'like', 'X.%')
                                ->orWhere('class_name', 'like', 'X-%');
                        })
                            ->where('class_name', 'not like', 'XI%')
                            ->where('class_name', 'not like', 'xi%');
                    });
            });

            return;
        }

        if (in_array($upper, ['11', 'XI'], true)) {
            $query->where(function (Builder $q) {
                $q->whereIn('class_name', ['11', 'XI', 'xi'])
                    ->orWhere('class_name', 'like', '11 %')
                    ->orWhere('class_name', 'like', '11.%')
                    ->orWhere('class_name', 'like', '11-%')
                    ->orWhere(function (Builder $sub) {
                        $sub->where(function (Builder $x) {
                            $x->where('class_name', 'like', 'XI %')
                                ->orWhere('class_name', 'like', 'XI.%')
                                ->orWhere('class_name', 'like', 'XI-%');
                        })
                            ->where('class_name', 'not like', 'XII%')
                            ->where('class_name', 'not like', 'xii%');
                    });
            });

            return;
        }

        if (in_array($upper, ['12', 'XII'], true)) {
            $query->where(function (Builder $q) {
                $q->whereIn('class_name', ['12', 'XII', 'xii'])
                    ->orWhere('class_name', 'like', '12 %')
                    ->orWhere('class_name', 'like', '12.%')
                    ->orWhere('class_name', 'like', '12-%')
                    ->orWhere('class_name', 'like', 'XII %')
                    ->orWhere('class_name', 'like', 'XII.%')
                    ->orWhere('class_name', 'like', 'XII-%');
            });

            return;
        }

        $query->where('class_name', $classFilter);
    }

    /**
     * Terapkan saringan jurusan, mencocokkan kolom `major` atau fallback pada `class_name`.
     *
     * @param  Builder<User>  $query
     */
    private function applyMajorFilter(Builder $query, string $majorFilter): void
    {
        $clean = trim($majorFilter);

        $query->where(function (Builder $q) use ($clean) {
            $q->where('major', $clean)
                ->orWhere('major', 'like', '%'.$clean.'%')
                ->orWhere(function (Builder $fallback) use ($clean) {
                    $fallback->where(function (Builder $nullCheck) {
                        $nullCheck->whereNull('major')->orWhere('major', '');
                    })->where('class_name', 'like', '%'.$clean.'%');
                });
        });
    }

    /**
     * Pilihan kelas diambil dari data yang sudah ada, ditambah kelas anggota
     * yang sedang diubah supaya tidak hilang dari daftar.
     *
     * @return Collection<int, string>
     */
    private function classOptions(?string $current = null): Collection
    {
        $options = User::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name');

        if (filled($current) && ! in_array(strtoupper((string) $current), ['10', '11', '12', 'X', 'XI', 'XII'], true) && ! $options->contains($current)) {
            $options->push($current);
            $options = $options->sort()->values();
        }

        return $options;
    }

    /**
     * Pilihan jurusan diambil murni dari data anggota yang sudah terdaftar di database
     * (otomatis menyesuaikan jurusan yang benar-benar ada, sama seperti kelas spesifik).
     *
     * @return Collection<int, string>
     */
    private function majorOptions(?string $current = null): Collection
    {
        $options = User::query()
            ->whereNotNull('major')
            ->where('major', '!=', '')
            ->distinct()
            ->orderBy('major')
            ->pluck('major')
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique(fn ($item) => mb_strtolower($item))
            ->sort()
            ->values();

        if (filled($current) && ! $options->contains(fn ($item) => mb_strtolower($item) === mb_strtolower((string) $current))) {
            $options->push(trim((string) $current));
            $options = $options->sort()->values();
        }

        return $options;
    }

    /**
     * Ekstrak kode jurusan dari nama kelas bila kolom jurusan tidak diisi terpisah.
     */
    private function extractMajorFromClassName(?string $className): ?string
    {
        if (blank($className)) {
            return null;
        }

        $upper = strtoupper(trim($className));

        if (preg_match('/^(?:10|11|12|XII|XI|X)\s*[-.]?\s*([A-Z]{2,12})\b/', $upper, $matches)) {
            return $matches[1];
        }

        foreach (self::DefaultMajors as $code) {
            if (preg_match('/\b'.preg_quote($code, '/').'\b/', $upper)) {
                return $code;
            }
        }

        return null;
    }
}
