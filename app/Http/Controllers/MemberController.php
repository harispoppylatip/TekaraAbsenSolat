<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\User;
use App\Services\FaceEnrollmentService;
use App\Services\SolatProofService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    /** Jumlah anggota per halaman pada daftar. */
    public const PerPage = 15;

    /** Pilihan pengurutan yang dikenal. */
    public const Sorts = ['name', 'identifier', 'role', 'recent'];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $query = User::query()
            ->search($filters['q'] ?: null)
            ->when($filters['role'] !== '', fn (Builder $inner) => $inner->where('role', $filters['role']))
            ->when($filters['class_name'] !== '', fn (Builder $inner) => $inner->where('class_name', $filters['class_name']))
            ->when($filters['face'] === 'ada', fn (Builder $inner) => $inner->has('faceEmbeddings'))
            ->when($filters['face'] === 'kosong', fn (Builder $inner) => $inner->doesntHave('faceEmbeddings'))
            ->withCount([
                'faceEmbeddings',
                'attendanceLogs' => fn (Builder $inner) => $inner->whereHas('session', fn (Builder $session) => $session->real()),
            ]);

        match ($filters['sort']) {
            'identifier' => $query->orderBy('identifier_number'),
            'role' => $query->orderBy('role')->orderBy('name'),
            'recent' => $query->orderByDesc('last_login_at')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        return view('members.index', [
            'members' => $query->paginate(self::PerPage)->withQueryString(),
            'filters' => $filters,
            'classes' => User::query()
                ->whereNotNull('class_name')
                ->where('class_name', '!=', '')
                ->distinct()
                ->orderBy('class_name')
                ->pluck('class_name'),
            'stats' => [
                'total' => User::query()->count(),
                'murid' => User::query()->where('role', User::RoleMurid)->count(),
                'petugas' => User::query()->whereIn('role', [User::RolePetugas, User::RoleAdmin])->count(),
                'berwajah' => User::query()->has('faceEmbeddings')->count(),
            ],
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

            fputcsv($handle, ['nama', 'nisn', 'email', 'peran', 'kelas'], $delimiter);
            fputcsv($handle, ['Ahmad Fauzi', '25001', '', 'murid', 'X TKJ 1'], $delimiter);
            fputcsv($handle, ['Siti Nurhaliza', '25002', 'siti@contoh.sch.id', 'murid', 'X TKJ 1'], $delimiter);
            fputcsv($handle, ['Bambang Irawan', 'GUR-001', 'bambang@contoh.sch.id', 'guru', ''], $delimiter);
            fputcsv($handle, ['Ratna Sari', 'ADM-002', 'ratna@contoh.sch.id', 'admin', ''], $delimiter);

            fclose($handle);
        }, 200, $headers);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'update_existing' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'Pilih berkas CSV terlebih dahulu.',
            'file.mimes' => 'Berkas harus berformat CSV atau teks (ekstensi .csv).',
            'file.max' => 'Ukuran berkas CSV maksimal 10 MB.',
        ]);

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->getRealPath();

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
            } elseif (in_array($normalized, ['kelas', 'class', 'class_name'], true)) {
                $headerMap['class_name'] = $idx;
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
                    if ($role === User::RoleMurid && $className !== null) {
                        $existing->class_name = $className;
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
                    'can_scan_face' => false,
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
            'mode' => 'create',
        ]);
    }

    public function store(MemberRequest $request, FaceEnrollmentService $faces): RedirectResponse
    {
        $data = $request->validated();

        $member = User::create([
            'name' => $data['name'],
            'identifier_number' => $data['identifier_number'],
            'email' => $data['email'],
            'role' => $data['role'],
            'class_name' => $data['class_name'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'can_scan_face' => $request->boolean('can_scan_face'),
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
            'mode' => 'edit',
        ]);
    }

    public function update(MemberRequest $request, User $member, FaceEnrollmentService $faces): RedirectResponse
    {
        $data = $request->validated();

        $member->fill([
            'name' => $data['name'],
            'identifier_number' => $data['identifier_number'],
            'email' => $data['email'],
            'role' => $data['role'],
            'class_name' => $data['class_name'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'can_scan_face' => $request->boolean('can_scan_face'),
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
     * Saringan dari alamat halaman, disaring memakai daftar putih supaya
     * kolom sembarang tidak bisa dipakai menyaring.
     *
     * @return array{q: string, role: string, class_name: string, face: string, sort: string}
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
            'face' => in_array($face, ['ada', 'kosong'], true) ? $face : '',
            'sort' => in_array($sort, self::Sorts, true) ? $sort : 'name',
        ];
    }

    /**
     * Pilihan kelas diambil dari data yang sudah ada, ditambah kelas anggota
     * yang sedang diubah supaya tidak hilang dari daftar.
     *
     * @return Collection<int, string>
     */
    private function classOptions(?string $current = null)
    {
        $options = User::query()
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name');

        if (filled($current) && ! $options->contains($current)) {
            $options->push($current);
            $options = $options->sort()->values();
        }

        return $options;
    }
}
