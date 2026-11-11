<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LaporanAbsensiController extends Controller
{
    public function index(Request $request)
    {
        $authUser       = auth()->user();
        $canAllBranches = $authUser->canAccessAllBranches();

        $cabangs  = $canAllBranches
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : collect();

        $cabangId    = $canAllBranches
            ? $request->input('cabang_id')
            : session('active_cabang_id');

        $dari        = $request->input('dari',    now()->startOfMonth()->toDateString());
        $sampai      = $request->input('sampai',  now()->endOfMonth()->toDateString());
        $statusFil   = $request->input('status',  'all');
        $karyawanFil = $request->input('karyawan_id');

        $query = Absensi::with([
                'karyawan', 'cabang',
                'faceAttendanceMasuk.device',
                'faceAttendanceKeluar.device',
                'faceAttendanceLemburMasuk.device',
                'faceAttendanceLemburKeluar.device',
            ])
            ->withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->whereBetween('tanggal', [$dari, $sampai]);

        if ($cabangId)                  $query->where('cabang_id', $cabangId);
        if ($karyawanFil)               $query->where('karyawan_id', $karyawanFil);
        if ($statusFil === 'hadir')     $query->whereNotNull('jam_masuk');
        elseif ($statusFil === 'ada_lembur') $query->whereNotNull('jam_lembur_masuk');
        elseif ($statusFil === 'telat') $query->where('keterangan', 'like', '%Terlambat%');

        // Search multi-field — TAMBAHAN, tidak mengganti filter status/karyawan di atas
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('karyawan', fn ($k) => $k->where('nama_lengkap', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")
                ->orWhere('jabatan', 'like', "%{$search}%"));
        }

        $records = $query->orderByDesc('tanggal')
            ->orderBy('karyawan_id')
            ->paginate(25)
            ->withQueryString();

        // Statistik (tanpa paginasi, hanya kolom yang dibutuhkan)
        $statsQuery = Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->select(['karyawan_id', 'jam_masuk', 'jam_lembur_masuk', 'keterangan']);
        if ($cabangId)    $statsQuery->where('cabang_id', $cabangId);
        if ($karyawanFil) $statsQuery->where('karyawan_id', $karyawanFil);

        $statsAll = $statsQuery->get();

        $stats = [
            'hari_hadir'    => $statsAll->whereNotNull('jam_masuk')->count(),
            'unik_karyawan' => $statsAll->pluck('karyawan_id')->unique()->filter()->count(),
            'hari_lembur'   => $statsAll->whereNotNull('jam_lembur_masuk')->count(),
            'hari_telat'    => $statsAll->filter(
                fn($a) => str_contains((string) $a->keterangan, 'Terlambat')
            )->count(),
        ];

        $karyawanList = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->aktif()
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'jabatan']);

        return view('laporan.absensi', compact(
            'records', 'stats', 'cabangs', 'karyawanList',
            'dari', 'sampai', 'statusFil', 'cabangId', 'karyawanFil',
            'canAllBranches'
        ));
    }

    public function exportExcel(Request $request)
    {
        $data     = $this->getExportData($request);
        $filename = 'laporan-absensi-' . $data['dari'] . '-sd-' . $data['sampai'] . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, [app(\App\Support\Branding::class)->namaSingkat() . ' — Laporan Absensi Wajah']);
            fputcsv($file, ['Periode: ' . $data['dari'] . ' s/d ' . $data['sampai']]);
            fputcsv($file, ['Dicetak: ' . now()->format('d/m/Y H:i')]);
            fputcsv($file, []);
            fputcsv($file, [
                'No', 'Tanggal', 'Nama Karyawan', 'Jabatan', 'Cabang',
                'Jam Masuk', 'Jam Keluar', 'Total Jam Kerja',
                'Jam Lembur Masuk', 'Jam Lembur Keluar', 'Total Jam Lembur',
                'Status', 'Keterangan',
            ]);

            foreach ($data['records'] as $i => $row) {
                $totalKerja  = $this->hitungDurasi($row->getRawOriginal('jam_masuk'), $row->getRawOriginal('jam_keluar'));
                $totalLembur = $this->hitungDurasi($row->getRawOriginal('jam_lembur_masuk'), $row->getRawOriginal('jam_lembur_keluar'));

                fputcsv($file, [
                    $i + 1,
                    $row->tanggal->format('d/m/Y'),
                    $row->karyawan?->nama_lengkap ?? '-',
                    $row->karyawan?->jabatan ?? '-',
                    $row->cabang?->nama_cabang ?? '-',
                    $row->jam_masuk ? substr($row->getRawOriginal('jam_masuk'), 0, 5) : '-',
                    $row->jam_keluar ? substr($row->getRawOriginal('jam_keluar'), 0, 5) : '-',
                    $totalKerja ?? '-',
                    $row->jam_lembur_masuk ? substr($row->getRawOriginal('jam_lembur_masuk'), 0, 5) : '-',
                    $row->jam_lembur_keluar ? substr($row->getRawOriginal('jam_lembur_keluar'), 0, 5) : '-',
                    $totalLembur ?? '-',
                    $this->labelStatus($row),
                    $row->keterangan ?? '',
                ]);
            }

            fputcsv($file, []);
            fputcsv($file, ['Total', $data['records']->count(), 'record']);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->getExportData($request);
        return view('laporan.absensi-pdf', $data);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getExportData(Request $request): array
    {
        $authUser       = auth()->user();
        $canAllBranches = $authUser->canAccessAllBranches();
        $cabangId       = $canAllBranches ? $request->input('cabang_id') : session('active_cabang_id');
        $dari           = $request->input('dari',   now()->startOfMonth()->toDateString());
        $sampai         = $request->input('sampai', now()->endOfMonth()->toDateString());
        $statusFil      = $request->input('status', 'all');
        $karyawanFil    = $request->input('karyawan_id');

        $query = Absensi::with([
                'karyawan', 'cabang',
                'faceAttendanceMasuk.device',
                'faceAttendanceKeluar.device',
                'faceAttendanceLemburMasuk.device',
                'faceAttendanceLemburKeluar.device',
            ])
            ->withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->whereBetween('tanggal', [$dari, $sampai]);

        if ($cabangId)                       $query->where('cabang_id', $cabangId);
        if ($karyawanFil)                    $query->where('karyawan_id', $karyawanFil);
        if ($statusFil === 'hadir')          $query->whereNotNull('jam_masuk');
        elseif ($statusFil === 'ada_lembur') $query->whereNotNull('jam_lembur_masuk');
        elseif ($statusFil === 'telat')      $query->where('keterangan', 'like', '%Terlambat%');

        $records = $query->orderByDesc('tanggal')->orderBy('karyawan_id')->get();
        $cabang  = $cabangId ? Cabang::find($cabangId) : null;

        return compact('records', 'dari', 'sampai', 'statusFil', 'cabang');
    }

    public function hitungDurasi(?string $masuk, ?string $keluar): ?string
    {
        if (!$masuk || !$keluar) return null;
        try {
            $m = Carbon::createFromFormat('H:i:s', substr($masuk, 0, 8));
            $k = Carbon::createFromFormat('H:i:s', substr($keluar, 0, 8));
            if ($k->lte($m)) return null;
            $menit = $m->diffInMinutes($k);
            return sprintf('%dj %02dm', intdiv($menit, 60), $menit % 60);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function labelStatus(Absensi $row): string
    {
        if (!$row->jam_masuk) return 'Tidak Hadir';
        $terlambat = str_contains((string) $row->keterangan, 'Terlambat');
        $lembur    = !empty($row->jam_lembur_masuk);
        if ($terlambat && $lembur) return 'Telat + Lembur';
        if ($terlambat)            return 'Telat';
        if ($lembur)               return 'Hadir + Lembur';
        return 'Hadir';
    }
}
