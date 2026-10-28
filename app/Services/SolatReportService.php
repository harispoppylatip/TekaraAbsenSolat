<?php

namespace App\Services;

use App\Models\SolatAttendanceLog;
use App\Models\SolatSession;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penyusun angka rekap kehadiran solat.
 *
 * Dipakai bersama oleh halaman Rekap di panel dan halaman riwayat milik murid,
 * supaya keduanya menghitung dengan cara yang sama.
 */
class SolatReportService
{
    /**
     * Angka ringkas untuk kartu di bagian atas halaman rekap.
     *
     * @return array<string, int>
     */
    public function summary(CarbonInterface $from, CarbonInterface $to, ?string $prayer = null): array
    {
        $sessions = SolatSession::query();
        $this->scopeSessions($sessions, $from, $to, $prayer);

        $logs = SolatAttendanceLog::query();
        $this->scopeLogs($logs, $from, $to, $prayer);

        return [
            'sessions' => (clone $sessions)->count(),
            'open_sessions' => (clone $sessions)->where('status', SolatSession::StatusSedangDibuka)->count(),
            'closed_sessions' => (clone $sessions)->where('status', SolatSession::StatusSudahDitutup)->count(),
            'logs' => (clone $logs)->count(),
            'present' => (clone $logs)->where('status', SolatAttendanceLog::StatusHadir)->count(),
            'late' => (clone $logs)->where('status', SolatAttendanceLog::StatusTerlambat)->count(),
            'members' => User::query()->where('role', '!=', User::RoleKiosk)->count(),
            'with_face' => User::query()->where('role', '!=', User::RoleKiosk)->has('faceEmbeddings')->count(),
        ];
    }

    /**
     * Rekap per anggota pada rentang tanggal tertentu.
     *
     * @param  array{term?: string|null, role?: string|null, class_name?: string|null, prayer?: string|null}  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function perMember(CarbonInterface $from, CarbonInterface $to, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $prayer = $filters['prayer'] ?? null;

        return User::query()
            ->where('role', '!=', User::RoleKiosk)
            ->search($filters['term'] ?? null)
            ->when(filled($filters['role'] ?? null), fn (Builder $query) => $query->where('role', $filters['role']))
            ->when(filled($filters['class_name'] ?? null), fn (Builder $query) => $query->where('class_name', $filters['class_name']))
            ->withCount([
                'attendanceLogs as present_count' => fn (Builder $query) => $this->scopeLogs($query, $from, $to, $prayer)
                    ->where('status', SolatAttendanceLog::StatusHadir),
                'attendanceLogs as late_count' => fn (Builder $query) => $this->scopeLogs($query, $from, $to, $prayer)
                    ->where('status', SolatAttendanceLog::StatusTerlambat),
                'attendanceLogs as log_count' => fn (Builder $query) => $this->scopeLogs($query, $from, $to, $prayer),
                'faceEmbeddings as face_count',
            ])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Rincian tiap sesi pada rentang tanggal, dipakai untuk melihat waktu
     * solat mana yang paling banyak bolong.
     *
     * @return Collection<int, SolatSession>
     */
    public function sessionBreakdown(CarbonInterface $from, CarbonInterface $to, int $limit = 30): Collection
    {
        return SolatSession::query()
            ->real()
            ->betweenDates($from, $to)
            ->withCount([
                'logs as log_count',
                'logs as present_count' => fn (Builder $query) => $query->where('status', SolatAttendanceLog::StatusHadir),
                'logs as late_count' => fn (Builder $query) => $query->where('status', SolatAttendanceLog::StatusTerlambat),
            ])
            ->orderByDesc('date')
            ->orderBy('start_time')
            ->limit($limit)
            ->get();
    }

    /**
     * Rincian tiap sesi pada rentang tanggal dengan pagination, 10 data terbaru per halaman.
     *
     * @return LengthAwarePaginator<int, SolatSession>
     */
    public function sessionBreakdownPaginated(CarbonInterface $from, CarbonInterface $to, int $perPage = 10, string $pageName = 'sessions_page'): LengthAwarePaginator
    {
        return SolatSession::query()
            ->real()
            ->betweenDates($from, $to)
            ->withCount([
                'logs as log_count',
                'logs as present_count' => fn (Builder $query) => $query->where('status', SolatAttendanceLog::StatusHadir),
                'logs as late_count' => fn (Builder $query) => $query->where('status', SolatAttendanceLog::StatusTerlambat),
            ])
            ->orderByDesc('date')
            ->orderBy('start_time')
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();
    }

    /**
     * Riwayat kehadiran satu anggota, dipakai portal murid.
     *
     * @return LengthAwarePaginator<int, SolatAttendanceLog>
     */
    public function memberHistory(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $user->attendanceLogs()
            ->whereHas('session', fn (Builder $query) => $query->real())
            ->with('session')
            ->orderByDesc('scanned_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Riwayat kehadiran per hari seorang anggota, dikelompokkan berdasarkan tanggal solat/pindai.
     *
     * @return LengthAwarePaginator<int, array{date: Carbon, date_string: string, logs: Collection<int, SolatAttendanceLog>, present_count: int, late_count: int, total_count: int}>
     */
    public function memberDailyHistory(User $user, ?CarbonInterface $from = null, ?CarbonInterface $to = null, int $perPage = 10): LengthAwarePaginator
    {
        $datesQuery = SolatAttendanceLog::query()
            ->where('user_id', $user->id)
            ->whereHas('session', fn (Builder $query) => $query->real())
            ->when($from, fn (Builder $q) => $q->where('scanned_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn (Builder $q) => $q->where('scanned_at', '<=', $to->copy()->endOfDay()))
            ->selectRaw('DATE(scanned_at) as log_date')
            ->distinct()
            ->orderByDesc('log_date');

        $paginatedDates = $datesQuery->paginate($perPage)->withQueryString();

        $dates = collect($paginatedDates->items())->pluck('log_date')->filter()->values()->all();

        if (empty($dates)) {
            $emptyPaginator = new LengthAwarePaginator(
                collect(),
                $paginatedDates->total(),
                $paginatedDates->perPage(),
                $paginatedDates->currentPage(),
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => $paginatedDates->getPageName(),
                ]
            );
            $emptyPaginator->withQueryString();

            return $emptyPaginator;
        }

        $logs = SolatAttendanceLog::query()
            ->where('user_id', $user->id)
            ->whereHas('session', fn (Builder $query) => $query->real())
            ->whereIn(DB::raw('DATE(scanned_at)'), $dates)
            ->with('session')
            ->orderBy('scanned_at')
            ->get()
            ->groupBy(fn (SolatAttendanceLog $log) => $log->scanned_at->toDateString());

        $days = collect($dates)->map(function (string $dateStr) use ($logs) {
            $dayLogs = $logs->get($dateStr, collect());
            $present = $dayLogs->where('status', SolatAttendanceLog::StatusHadir)->count();
            $late = $dayLogs->where('status', SolatAttendanceLog::StatusTerlambat)->count();

            return [
                'date' => Carbon::parse($dateStr),
                'date_string' => $dateStr,
                'logs' => $dayLogs,
                'present_count' => $present,
                'late_count' => $late,
                'total_count' => $dayLogs->count(),
            ];
        });

        $paginator = new LengthAwarePaginator(
            $days,
            $paginatedDates->total(),
            $paginatedDates->perPage(),
            $paginatedDates->currentPage(),
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => $paginatedDates->getPageName(),
            ]
        );
        $paginator->withQueryString();

        return $paginator;
    }

    /**
     * Sesi uji coba tidak pernah ikut dihitung, supaya percobaan tidak
     * mengotori angka rekap yang sungguhan.
     *
     * @param  Builder<SolatSession>  $query
     */
    private function scopeSessions(Builder $query, CarbonInterface $from, CarbonInterface $to, ?string $prayer = null): void
    {
        $query->real();
        $query->betweenDates($from, $to);

        if (filled($prayer)) {
            $query->where('prayer_name', $prayer);
        }
    }

    /**
     * @param  Builder<SolatAttendanceLog>  $query
     */
    private function scopeLogs(Builder $query, CarbonInterface $from, CarbonInterface $to, ?string $prayer = null): Builder
    {
        return $query->whereHas('session', fn (Builder $session) => $this->scopeSessions($session, $from, $to, $prayer));
    }
}
