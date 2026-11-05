<?php

namespace App\Http\Controllers;

use App\Models\SolatAttendanceLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceHistoryController extends Controller
{
    public const PerPage = 15;

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $logScope = function (Builder $logQuery) use ($filters): void {
            $logQuery->whereHas('session', fn (Builder $s) => $s->real());

            if ($filters['date_from'] !== '') {
                $logQuery->whereDate('scanned_at', '>=', $filters['date_from']);
            }

            if ($filters['date_to'] !== '') {
                $logQuery->whereDate('scanned_at', '<=', $filters['date_to']);
            }

            if ($filters['method'] === 'wajah') {
                $logQuery->where(function (Builder $q) {
                    $q->whereNotNull('proof_photo_path')
                        ->orWhereIn('device_id', array_filter([
                            config('solat.device_id', 'kamera-wajah'),
                            config('face.device_id', 'kamera-wajah'),
                            'kamera-wajah',
                        ]))
                        ->orWhere('device_id', 'like', 'cctv-%')
                        ->orWhere('device_id', 'like', 'kamera-%');
                });
            } elseif ($filters['method'] === 'fingerprint') {
                $cameraDevices = array_values(array_filter([
                    config('solat.device_id', 'kamera-wajah'),
                    config('face.device_id', 'kamera-wajah'),
                    'kamera-wajah',
                ]));

                $logQuery->whereNull('proof_photo_path')
                    ->whereNotNull('device_id')
                    ->where('device_id', '!=', '')
                    ->whereNotIn('device_id', $cameraDevices)
                    ->where('device_id', 'not like', 'cctv-%')
                    ->where('device_id', 'not like', 'kamera-%');
            }
        };

        $query = User::query()
            ->search($filters['q'] ?: null)
            ->when($filters['class_name'] !== '', fn (Builder $inner) => $this->applyClassFilter($inner, $filters['class_name']))
            ->when($filters['major'] !== '', fn (Builder $inner) => $this->applyMajorFilter($inner, $filters['major']))
            ->whereHas('attendanceLogs', $logScope)
            ->withCount([
                'attendanceLogs as total_hadir_count' => $logScope,
                'attendanceLogs as tepat_waktu_count' => function (Builder $q) use ($logScope) {
                    $logScope($q);
                    $q->where('status', SolatAttendanceLog::StatusHadir);
                },
                'attendanceLogs as terlambat_count' => function (Builder $q) use ($logScope) {
                    $logScope($q);
                    $q->where('status', SolatAttendanceLog::StatusTerlambat);
                },
            ])
            ->withMax(['attendanceLogs as latest_scanned_at' => $logScope], 'scanned_at')
            ->with([
                'attendanceLogs' => function ($q) use ($logScope) {
                    $logScope($q);
                    $q->with('session')
                        ->orderByDesc('scanned_at')
                        ->orderByDesc('id');
                },
            ]);

        match ($filters['sort']) {
            'name' => $query->orderBy('name'),
            'most' => $query->orderByDesc('total_hadir_count')->orderBy('name'),
            default => $query->orderByDesc('latest_scanned_at')->orderBy('name'),
        };

        $totalMembersAttended = User::query()
            ->whereHas('attendanceLogs', fn (Builder $q) => $q->whereHas('session', fn (Builder $s) => $s->real()))
            ->count();

        $totalLogs = SolatAttendanceLog::query()
            ->whereHas('session', fn (Builder $s) => $s->real())
            ->count();

        $totalWithPhoto = SolatAttendanceLog::query()
            ->whereHas('session', fn (Builder $s) => $s->real())
            ->whereNotNull('proof_photo_path')
            ->where('proof_photo_path', '!=', '')
            ->count();

        $cameraDevices = array_values(array_filter([
            config('solat.device_id', 'kamera-wajah'),
            config('face.device_id', 'kamera-wajah'),
            'kamera-wajah',
        ]));

        $totalFingerprint = SolatAttendanceLog::query()
            ->whereHas('session', fn (Builder $s) => $s->real())
            ->whereNull('proof_photo_path')
            ->whereNotNull('device_id')
            ->where('device_id', '!=', '')
            ->whereNotIn('device_id', $cameraDevices)
            ->where('device_id', 'not like', 'cctv-%')
            ->where('device_id', 'not like', 'kamera-%')
            ->count();

        return view('history.index', [
            'members' => $query->paginate(self::PerPage)->withQueryString(),
            'filters' => $filters,
            'classes' => $this->classOptions($filters['class_name']),
            'majors' => $this->majorOptions($filters['major']),
            'stats' => [
                'members_attended' => $totalMembersAttended,
                'total_logs' => $totalLogs,
                'face_logs' => $totalWithPhoto,
                'fingerprint_logs' => $totalFingerprint,
            ],
        ]);
    }

    public function show(Request $request, User $member): View
    {
        $method = (string) $request->query('method', '');
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $logsQuery = SolatAttendanceLog::query()
            ->where('user_id', $member->id)
            ->whereHas('session', fn (Builder $s) => $s->real())
            ->with('session')
            ->when($dateFrom !== '', fn (Builder $q) => $q->whereDate('scanned_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $q) => $q->whereDate('scanned_at', '<=', $dateTo))
            ->orderByDesc('scanned_at')
            ->orderByDesc('id');

        if ($method === 'wajah') {
            $logsQuery->where(function (Builder $q) {
                $q->whereNotNull('proof_photo_path')
                    ->orWhereIn('device_id', array_filter([
                        config('solat.device_id', 'kamera-wajah'),
                        config('face.device_id', 'kamera-wajah'),
                        'kamera-wajah',
                    ]))
                    ->orWhere('device_id', 'like', 'cctv-%')
                    ->orWhere('device_id', 'like', 'kamera-%');
            });
        } elseif ($method === 'fingerprint') {
            $cameraDevices = array_values(array_filter([
                config('solat.device_id', 'kamera-wajah'),
                config('face.device_id', 'kamera-wajah'),
                'kamera-wajah',
            ]));

            $logsQuery->whereNull('proof_photo_path')
                ->whereNotNull('device_id')
                ->where('device_id', '!=', '')
                ->whereNotIn('device_id', $cameraDevices)
                ->where('device_id', 'not like', 'cctv-%')
                ->where('device_id', 'not like', 'kamera-%');
        }

        $logs = $logsQuery->paginate(30)->withQueryString();

        return view('history.show', [
            'member' => $member,
            'logs' => $logs,
            'filters' => [
                'method' => in_array($method, ['wajah', 'fingerprint'], true) ? $method : '',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * @return array{q: string, class_name: string, major: string, method: string, date_from: string, date_to: string, sort: string}
     */
    private function filters(Request $request): array
    {
        $method = (string) $request->query('method', '');
        $sort = (string) $request->query('sort', 'latest');

        return [
            'q' => trim((string) $request->query('q', '')),
            'class_name' => trim((string) $request->query('class_name', '')),
            'major' => trim((string) $request->query('major', '')),
            'method' => in_array($method, ['wajah', 'fingerprint'], true) ? $method : '',
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
            'sort' => in_array($sort, ['latest', 'name', 'most'], true) ? $sort : 'latest',
        ];
    }

    /**
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
}
