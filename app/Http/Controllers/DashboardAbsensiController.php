<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardAbsensiController extends Controller
{
    public function index(Request $request)
    {
        $authUser  = auth()->user();
        $tanggal   = $request->filled('tanggal')
            ? Carbon::parse($request->tanggal)
            : Carbon::today();

        // Pilih cabang
        $cabangs = $authUser->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : collect();

        if ($authUser->canAccessAllBranches()) {
            $cabangId = $request->filled('cabang_id')
                ? (int) $request->cabang_id
                : ($cabangs->first()?->id);
        } else {
            $cabangId = session('active_cabang_id');
        }

        $cabang = $cabangId ? Cabang::find($cabangId) : null;

        // Karyawan aktif di cabang terpilih
        $karyawans = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->with(['shift', 'cabang'])
            ->where('cabang_id', $cabangId)
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get();

        // Absensi hari ini untuk cabang terpilih
        $absensiHariIni = Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('karyawan_id');

        // Bangun data per-karyawan
        $filterStatus = $request->input('status_filter', '');
        $rows = $karyawans->map(function (Karyawan $k) use ($absensiHariIni, $tanggal) {
            $abs = $absensiHariIni->get($k->id);
            return [
                'karyawan' => $k,
                'absensi'  => $abs,
                'status'   => $this->resolveStatus($k, $abs, $tanggal),
            ];
        });

        if ($filterStatus) {
            $rows = $rows->filter(fn($r) => $r['status'] === $filterStatus)->values();
        }

        // Statistik
        $allRows = $karyawans->map(fn($k) => [
            'status' => $this->resolveStatus($k, $absensiHariIni->get($k->id), $tanggal),
        ]);
        $stats = [
            'total'        => $karyawans->count(),
            'hadir'        => $allRows->whereIn('status', ['hadir_tepat', 'sedang_kerja', 'sedang_lembur'])->count(),
            'telat'        => $allRows->where('status', 'telat')->count(),
            'belum_masuk'  => $allRows->where('status', 'belum_masuk')->count(),
            'tidak_hadir'  => $allRows->where('status', 'tidak_hadir')->count(),
            'sedang_lembur'=> $allRows->where('status', 'sedang_lembur')->count(),
        ];

        // Overview per cabang (hanya owner)
        $overviewCabang = [];
        if ($authUser->canAccessAllBranches()) {
            $overviewCabang = $this->getOverviewPerCabang($tanggal);
        }

        return view('dashboard-absensi.index', compact(
            'rows', 'stats', 'tanggal', 'cabang', 'cabangs', 'cabangId',
            'filterStatus', 'overviewCabang'
        ));
    }

    private function resolveStatus(Karyawan $karyawan, ?Absensi $absensi, Carbon $tanggal): string
    {
        if (!$absensi) {
            // Belum ada record: jika hari ini = today → belum masuk; jika masa lalu → tidak hadir
            return $tanggal->isToday() ? 'belum_masuk' : 'tidak_hadir';
        }

        if (in_array($absensi->status, ['izin', 'sakit', 'cuti', 'libur'])) {
            return 'tidak_hadir';
        }

        if ($absensi->jam_masuk) {
            if ($absensi->jam_lembur_masuk && !$absensi->jam_lembur_keluar) {
                return 'sedang_lembur';
            }
            if (!$absensi->jam_keluar) {
                return $absensi->is_telat ? 'telat' : 'sedang_kerja';
            }
            return $absensi->is_telat ? 'telat' : 'hadir_tepat';
        }

        return 'belum_masuk';
    }

    private function getOverviewPerCabang(Carbon $tanggal): array
    {
        $cabangs  = Cabang::aktif()->orderBy('nama_cabang')->get();
        $overview = [];

        foreach ($cabangs as $c) {
            $total = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                ->where('cabang_id', $c->id)->where('status', 'aktif')->count();

            $absensis = Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                ->where('cabang_id', $c->id)
                ->whereDate('tanggal', $tanggal)
                ->get();

            $hadir = $absensis->whereNotNull('jam_masuk')->count();
            $telat = $absensis->where('is_telat', true)->count();

            $overview[] = [
                'cabang'  => $c,
                'total'   => $total,
                'hadir'   => $hadir,
                'telat'   => $telat,
                'belum'   => max(0, $total - $hadir),
                'persen'  => $total > 0 ? round($hadir / $total * 100) : 0,
            ];
        }

        return $overview;
    }
}
