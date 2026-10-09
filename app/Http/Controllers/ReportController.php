<?php

namespace App\Http\Controllers;

use App\Models\PrayerTime;
use App\Models\User;
use App\Services\SolatReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Jumlah anggota per halaman pada tabel rekap. */
    public const PerPage = 20;

    public function index(Request $request, SolatReportService $reports): View
    {
        [$from, $to] = $this->range($request);
        $prayer = $this->prayer($request);

        $filters = [
            'term' => trim((string) $request->query('q', '')),
            'role' => in_array((string) $request->query('role'), array_keys(User::roles()), true)
                ? (string) $request->query('role')
                : '',
            'class_name' => trim((string) $request->query('class_name', '')),
            'prayer' => $prayer,
        ];

        return view('reports.index', [
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'summary' => $reports->summary($from, $to, $prayer),
            'members' => $reports->perMember($from, $to, $filters, self::PerPage),
            'breakdown' => $reports->sessionBreakdown($from, $to),
            'prayers' => PrayerTime::query()->ordered()->get(),
            'classes' => User::query()
                ->whereNotNull('class_name')
                ->where('class_name', '!=', '')
                ->distinct()
                ->orderBy('class_name')
                ->pluck('class_name'),
        ]);
    }

    public function export(Request $request, SolatReportService $reports): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $prayer = $this->prayer($request);

        $filters = [
            'term' => trim((string) $request->query('q', '')),
            'role' => in_array((string) $request->query('role'), array_keys(User::roles()), true)
                ? (string) $request->query('role')
                : '',
            'class_name' => trim((string) $request->query('class_name', '')),
            'prayer' => $prayer,
        ];

        $rows = $reports->perMember($from, $to, $filters, 100000);
        $filename = 'rekap-solat-'.$from->toDateString().'-sampai-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows, $reports, $from, $to, $prayer): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Rekap Kehadiran Solat']);
            fputcsv($handle, ['Sekolah', config('app.name')]);
            fputcsv($handle, ['Rentang', $from->format('d/m/Y').' sampai '.$to->format('d/m/Y')]);
            fputcsv($handle, ['Waktu solat', $prayer !== '' ? $prayer : 'Semua waktu']);
            fputcsv($handle, []);

            fputcsv($handle, ['Nomor induk', 'Nama', 'Peran', 'Kelas', 'Hadir', 'Terlambat', 'Total catatan', 'Foto wajah']);

            foreach ($rows as $member) {
                fputcsv($handle, [
                    $member->identifier_number,
                    $member->name,
                    $member->roleLabel(),
                    $member->class_name ?? '-',
                    $member->present_count,
                    $member->late_count,
                    $member->log_count,
                    $member->face_count,
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Sesi', 'Jumlah catatan', 'Hadir', 'Terlambat']);

            foreach ($reports->sessionBreakdown($from, $to, 500) as $session) {
                fputcsv($handle, [
                    $session->label(),
                    $session->log_count,
                    $session->present_count,
                    $session->late_count,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $this->parse($request->query('from')) ?? Carbon::today()->subDays(6);
        $to = $this->parse($request->query('to')) ?? Carbon::today();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    private function parse(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function prayer(Request $request): string
    {
        $value = trim((string) $request->query('prayer', ''));

        if ($value === '') {
            return '';
        }

        return PrayerTime::query()->where('prayer_name', $value)->exists() ? $value : '';
    }
}
