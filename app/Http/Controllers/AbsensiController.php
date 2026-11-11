<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    /**
     * Form input absensi harian
     */
    public function index(Request $request)
    {
        $tanggal   = $request->filled('tanggal') ? Carbon::parse($request->tanggal) : Carbon::today();
        $cabangId  = session('active_cabang_id');
        $authUser  = auth()->user();

        // Daftar karyawan aktif di cabang
        $karyawans = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('cabang_id', $cabangId)
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get();

        // Absensi yang sudah ada untuk tanggal tersebut
        $existingAbsensi = Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('karyawan_id');

        return view('absensi.index', compact('karyawans', 'tanggal', 'existingAbsensi'));
    }

    /**
     * Simpan absensi batch (multiple karyawan)
     */
    public function store(Request $request)
    {
        $request->validate([
            'tanggal'               => ['required', 'date'],
            'absensi'               => ['required', 'array'],
            'absensi.*.karyawan_id' => ['required', 'exists:karyawans,id'],
            'absensi.*.status'      => ['required', 'in:hadir,izin,sakit,alpha,libur,cuti'],
        ]);

        $tanggal  = $request->tanggal;
        $cabangId = session('active_cabang_id');
        $userId   = auth()->id();
        $count    = 0;

        // Preload cabang untuk fallback jam_masuk
        $cabang = \App\Models\Cabang::find($cabangId);

        foreach ($request->absensi as $row) {
            if (empty($row['karyawan_id'])) continue;

            $karyawan  = \App\Models\Karyawan::with('shift')
                ->find($row['karyawan_id']);
            $jamMasuk  = !empty($row['jam_masuk']) ? $row['jam_masuk'] : null;

            // Deteksi telat untuk status hadir dengan jam_masuk diisi
            $isTelat   = false;
            $menitTelat = 0;
            if ($row['status'] === 'hadir' && $jamMasuk && $karyawan) {
                [$isTelat, $menitTelat] = $this->hitungKeterlambatan($karyawan, $cabang, $jamMasuk . ':00');
            }

            Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                ->updateOrCreate(
                    ['karyawan_id' => $row['karyawan_id'], 'tanggal' => $tanggal],
                    [
                        'cabang_id'          => $cabangId,
                        'status'             => $row['status'],
                        'is_telat'           => $isTelat,
                        'menit_telat'        => $menitTelat,
                        'jam_masuk'          => $jamMasuk,
                        'jam_keluar'         => !empty($row['jam_keluar'])         ? $row['jam_keluar']         : null,
                        'jam_lembur_masuk'   => !empty($row['jam_lembur_masuk'])   ? $row['jam_lembur_masuk']   : null,
                        'jam_lembur_keluar'  => !empty($row['jam_lembur_keluar'])  ? $row['jam_lembur_keluar']  : null,
                        'keterangan'         => $row['keterangan'] ?? null,
                        'dicatat_oleh'       => $userId,
                    ]
                );
            $count++;
        }

        return redirect()->route('absensi.index', ['tanggal' => $tanggal])
            ->with('success', "Absensi {$count} karyawan berhasil disimpan untuk tanggal " . Carbon::parse($tanggal)->format('d/m/Y') . ".");
    }

    /**
     * Edit 1 record absensi
     */
    public function edit(Absensi $absensi)
    {
        $absensi->load('karyawan');
        return view('absensi.edit', compact('absensi'));
    }

    /**
     * Update 1 record absensi
     */
    public function update(Request $request, Absensi $absensi)
    {
        $request->validate([
            'status'            => ['required', 'in:hadir,izin,sakit,alpha,libur,cuti'],
            'jam_masuk'         => ['nullable', 'date_format:H:i'],
            'jam_keluar'        => ['nullable', 'date_format:H:i'],
            'jam_lembur_masuk'  => ['nullable', 'date_format:H:i'],
            'jam_lembur_keluar' => ['nullable', 'date_format:H:i'],
            'keterangan'        => ['nullable', 'string', 'max:500'],
        ]);

        $absensi->update([
            'status'            => $request->status,
            'jam_masuk'         => $request->jam_masuk,
            'jam_keluar'        => $request->jam_keluar,
            'jam_lembur_masuk'  => $request->jam_lembur_masuk,
            'jam_lembur_keluar' => $request->jam_lembur_keluar,
            'keterangan'        => $request->keterangan,
            'dicatat_oleh'      => auth()->id(),
        ]);

        return redirect()->route('absensi.index', ['tanggal' => $absensi->tanggal->format('Y-m-d')])
            ->with('success', 'Absensi berhasil diperbarui.');
    }

    /**
     * [bool $isTelat, int $menitTelat] — berdasarkan shift karyawan atau jam_masuk cabang.
     */
    private function hitungKeterlambatan(\App\Models\Karyawan $karyawan, ?\App\Models\Cabang $cabang, string $jamSekarang): array
    {
        $shift = $karyawan->shift;

        if ($shift) {
            $jamMasukShift  = substr($shift->jam_masuk, 0, 5);
            $toleransiMenit = (int) ($shift->toleransi_telat_menit ?? 15);
        } else {
            $jamMasukShift  = substr($cabang?->jam_masuk ?? '08:00', 0, 5);
            $toleransiMenit = 15;
        }

        $batasToleransi = \Carbon\Carbon::today()
            ->setTimeFromTimeString($jamMasukShift)
            ->addMinutes($toleransiMenit);
        $waktuAbsen = \Carbon\Carbon::today()->setTimeFromTimeString(substr($jamSekarang, 0, 8));

        if ($waktuAbsen->gt($batasToleransi)) {
            return [true, (int) $batasToleransi->diffInMinutes($waktuAbsen)];
        }
        return [false, 0];
    }

    /**
     * Hapus 1 record absensi (permission: hapus_absensi)
     */
    public function destroy(Absensi $absensi)
    {
        abort_unless(auth()->user()->can('hapus_absensi'), 403, 'Anda tidak memiliki akses untuk menghapus absensi.');

        $karyawanNama = $absensi->karyawan?->nama_lengkap ?? 'Karyawan';
        $tanggal      = $absensi->tanggal->format('d/m/Y');

        $absensi->delete();

        return back()->with('success', "Absensi {$karyawanNama} tanggal {$tanggal} berhasil dihapus.");
    }

    /**
     * Rekap absensi bulanan
     */
    public function rekap(Request $request)
    {
        $bulan    = $request->input('bulan', now()->month);
        $tahun    = $request->input('tahun', now()->year);
        $cabangId = session('active_cabang_id');
        $authUser = auth()->user();

        $periodeStr = sprintf('%04d-%02d', $tahun, $bulan);
        $tanggalAwal  = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $tanggalAkhir = $tanggalAwal->copy()->endOfMonth();

        $karyawansQuery = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->with(['absensis' => function ($q) use ($tanggalAwal, $tanggalAkhir) {
                $q->whereBetween('tanggal', [$tanggalAwal, $tanggalAkhir]);
            }]);

        if (!$authUser->canAccessAllBranches()) {
            $karyawansQuery->where('cabang_id', $cabangId);
        } elseif ($request->filled('cabang_id')) {
            $karyawansQuery->where('cabang_id', $request->cabang_id);
            $cabangId = $request->cabang_id;
        }

        $karyawans = $karyawansQuery->orderBy('nama_lengkap')->get();

        $rekap = $karyawans->map(function ($k) {
            $abs = $k->absensis;
            return [
                'karyawan'   => $k,
                'hadir'      => $abs->where('status', 'hadir')->count(),
                'izin'       => $abs->where('status', 'izin')->count(),
                'sakit'      => $abs->where('status', 'sakit')->count(),
                'alpha'      => $abs->where('status', 'alpha')->count(),
                'libur'      => $abs->where('status', 'libur')->count(),
                'cuti'       => $abs->where('status', 'cuti')->count(),
                'jam_lembur' => $abs->sum('jam_lembur'),
            ];
        });

        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('absensi.rekap', compact('rekap', 'bulan', 'tahun', 'cabangs', 'cabangId'));
    }
}
