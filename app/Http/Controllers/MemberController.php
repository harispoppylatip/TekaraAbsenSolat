<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRequest;
use App\Models\User;
use App\Services\FaceEnrollmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

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
            ->withCount(['faceEmbeddings', 'attendanceLogs']);

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
            'password' => $data['password'] ?? User::defaultPasswordFor($data['role'], $data['identifier_number']),
        ]);

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
            $member->password_changed_at = null;
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
