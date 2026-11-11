<?php

namespace App\Http\Controllers;

use App\Http\Requests\PenggajianGenerateRequest;
use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\PengaturanGaji;
use App\Models\Scopes\CabangScope;
use App\Notifications\SlipGajiNotification;
use App\Services\NotificationService;
use App\Services\PenggajianService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PenggajianController extends Controller
{
    public function __construct(private PenggajianService $penggajianService) {}

    /**
     * Daftar slip gaji
     */
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $query = Penggajian::with(['karyawan', 'cabang'])
            ->withoutGlobalScope(\App\Models\Scopes\CabangScope::class);

        // Filter cabang
        if (!$authUser->canAccessAllBranches()) {
            $query->where('cabang_id', session('active_cabang_id'));
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        // Filter periode
        if ($request->filled('periode')) {
            $query->where('periode', $request->periode);
        }

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search multi-field — TAMBAHAN, tidak mengganti filter cabang/periode/status di atas
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('karyawan', fn ($k) => $k->where('nama_lengkap', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%"));
        }

        $penggajians = $query->orderByDesc('periode')->orderBy('karyawan_id')->paginate(15)->withQueryString();
        $cabangs     = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('penggajian.index', compact('penggajians', 'cabangs'));
    }

    /**
     * Form generate gaji
     */
    public function generate()
    {
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();
        $periodeDefault = now()->subMonth()->format('Y-m');

        return view('penggajian.generate', compact('cabangs', 'periodeDefault'));
    }

    /**
     * Proses generate gaji bulk
     */
    public function prosesGenerate(PenggajianGenerateRequest $request)
    {
        $override = [
            'tunjangan'    => $request->tunjangan ?? 0,
            'bonus'        => $request->bonus ?? 0,
            'potongan_lain' => $request->potongan_lain ?? 0,
        ];

        $count = $this->penggajianService->bulkGenerate(
            (int) $request->cabang_id,
            $request->periode,
            $override
        );

        // Notifikasi ke semua karyawan di cabang tersebut
        $penggajians = Penggajian::where('cabang_id', $request->cabang_id)
            ->where('periode', $request->periode)
            ->with('karyawan.user')
            ->get();
        foreach ($penggajians as $pg) {
            if ($pg->karyawan?->user_id) {
                $user = \App\Models\User::find($pg->karyawan->user_id);
                if ($user) {
                    NotificationService::send(collect([$user]), new SlipGajiNotification($request->periode, $pg->id));
                }
            }
        }

        return redirect()->route('penggajian.index', ['periode' => $request->periode])
            ->with('success', "Berhasil generate {$count} slip gaji untuk periode {$request->periode}.");
    }

    /**
     * Detail 1 slip gaji
     */
    public function show(Penggajian $penggajian)
    {
        $penggajian->load(['karyawan.cabang', 'cabang', 'approvedBy']);
        return view('penggajian.show', compact('penggajian'));
    }

    /**
     * Cetak slip gaji (print-friendly)
     */
    public function cetak(Penggajian $penggajian)
    {
        $penggajian->load(['karyawan', 'cabang', 'approvedBy']);
        return view('penggajian.cetak', compact('penggajian'));
    }

    /**
     * Approve slip gaji
     */
    public function approve(Penggajian $penggajian)
    {
        if ($penggajian->status !== 'draft') {
            return back()->with('error', 'Slip gaji ini tidak bisa di-approve.');
        }

        $penggajian->update([
            'status'      => 'disetujui',
            'approved_by' => auth()->id(),
        ]);

        return back()->with('success', 'Slip gaji berhasil disetujui.');
    }

    /**
     * Tandai dibayar
     */
    public function bayar(Penggajian $penggajian)
    {
        if (!in_array($penggajian->status, ['draft', 'disetujui'])) {
            return back()->with('error', 'Slip gaji ini sudah dibayar atau tidak valid.');
        }

        $penggajian->update([
            'status'       => 'dibayar',
            'tanggal_bayar' => today(),
        ]);

        return back()->with('success', 'Slip gaji berhasil ditandai sebagai dibayar.');
    }

    /**
     * Form buat slip gaji per karyawan
     */
    public function createSingle(Request $request)
    {
        $authUser  = auth()->user();
        $karyawans = Karyawan::withoutGlobalScope(CabangScope::class)
            ->when(!$authUser->canAccessAllBranches(), fn($q) => $q->where('cabang_id', session('active_cabang_id')))
            ->aktif()->orderBy('nama_lengkap')->get();

        $periodeDefault    = $request->periode ?? now()->subMonth()->format('Y-m');
        $selectedKaryawan  = null;
        $preview           = null;

        if ($request->filled('karyawan_id') && $request->filled('periode')) {
            $selectedKaryawan = Karyawan::withoutGlobalScope(CabangScope::class)->find($request->karyawan_id);

            if ($selectedKaryawan) {
                // Jika sudah ada slip gaji, redirect ke edit
                $existing = Penggajian::withoutGlobalScope(CabangScope::class)
                    ->where('karyawan_id', $selectedKaryawan->id)
                    ->where('periode', $request->periode)
                    ->first();

                if ($existing) {
                    return redirect()->route('penggajian.edit', $existing)
                        ->with('info', 'Slip gaji untuk periode ini sudah ada. Silakan edit.');
                }

                $preview = $this->buildPreview($selectedKaryawan, $request->periode);
            }
        }

        return view('penggajian.form', [
            'karyawans'       => $karyawans,
            'periodeDefault'  => $periodeDefault,
            'selectedKaryawan'=> $selectedKaryawan,
            'preview'         => $preview,
            'penggajian'      => null,
            'isEdit'          => false,
        ]);
    }

    /**
     * Simpan slip gaji per karyawan (manual)
     */
    public function storeSingle(Request $request)
    {
        $request->validate([
            'karyawan_id' => 'required|exists:karyawans,id',
            'periode'     => 'required|date_format:Y-m',
        ]);

        $karyawan = Karyawan::withoutGlobalScope(CabangScope::class)->findOrFail($request->karyawan_id);

        $override = array_filter([
            'tunjangan_jabatan'    => $request->input('tunjangan_jabatan'),
            'tunjangan_makan'      => $request->input('tunjangan_makan'),
            'tunjangan_transport'  => $request->input('tunjangan_transport'),
            'tunjangan_kehadiran'  => $request->input('tunjangan_kehadiran'),
            'bpjs_kesehatan'       => $request->input('bpjs_kesehatan'),
            'bpjs_ketenagakerjaan' => $request->input('bpjs_ketenagakerjaan'),
            'bonus'                => $request->input('bonus'),
            'insentif'             => $request->input('insentif'),
            'thr'                  => $request->input('thr'),
            'komisi'               => $request->input('komisi'),
            'tunjangan'            => $request->input('tunjangan'),
            'pph21'                => $request->input('pph21'),
            'kasbon'               => $request->input('kasbon'),
            'potongan_lain'        => $request->input('potongan_lain'),
        ], fn($v) => $v !== null && $v !== '');

        // Pass manual override values only if flags are set (strip dots in case JS onSubmit didn't run)
        if ($request->input('uang_lembur_manual') === '1') {
            $override['uang_lembur'] = (int) str_replace('.', '', $request->input('uang_lembur', '0'));
        }
        if ($request->input('potongan_alpa_manual') === '1') {
            $override['potongan_absensi'] = (int) str_replace('.', '', $request->input('potongan_absensi', '0'));
        }

        $penggajian = $this->penggajianService->generate($karyawan, $request->periode, $override);

        if ($request->filled('catatan')) {
            $penggajian->update(['catatan' => $request->catatan]);
        }

        $user = \App\Models\User::find($karyawan->user_id);
        if ($user) {
            NotificationService::send(collect([$user]), new SlipGajiNotification($request->periode, $penggajian->id));
        }

        return redirect()->route('penggajian.show', $penggajian)
            ->with('success', "Slip gaji {$karyawan->nama_lengkap} periode {$request->periode} berhasil dibuat.");
    }

    /**
     * Form edit slip gaji (hanya draft)
     */
    public function edit(Penggajian $penggajian)
    {
        if ($penggajian->status !== 'draft') {
            return redirect()->route('penggajian.show', $penggajian)
                ->with('error', 'Hanya slip gaji berstatus draft yang bisa diedit.');
        }

        $penggajian->load('karyawan');
        $authUser  = auth()->user();
        $karyawans = Karyawan::withoutGlobalScope(CabangScope::class)
            ->when(!$authUser->canAccessAllBranches(), fn($q) => $q->where('cabang_id', session('active_cabang_id')))
            ->aktif()->orderBy('nama_lengkap')->get();

        return view('penggajian.form', [
            'karyawans'        => $karyawans,
            'periodeDefault'   => $penggajian->periode,
            'selectedKaryawan' => $penggajian->karyawan,
            'preview'          => null,
            'penggajian'       => $penggajian,
            'isEdit'           => true,
        ]);
    }

    /**
     * Update slip gaji (hanya draft)
     */
    public function update(Request $request, Penggajian $penggajian)
    {
        if ($penggajian->status !== 'draft') {
            return back()->with('error', 'Hanya slip gaji berstatus draft yang bisa diedit.');
        }

        $request->validate(['periode' => 'required|date_format:Y-m']);

        $karyawan = Karyawan::withoutGlobalScope(CabangScope::class)->findOrFail($penggajian->karyawan_id);

        $override = array_filter([
            'tunjangan_jabatan'    => $request->input('tunjangan_jabatan'),
            'tunjangan_makan'      => $request->input('tunjangan_makan'),
            'tunjangan_transport'  => $request->input('tunjangan_transport'),
            'tunjangan_kehadiran'  => $request->input('tunjangan_kehadiran'),
            'bpjs_kesehatan'       => $request->input('bpjs_kesehatan'),
            'bpjs_ketenagakerjaan' => $request->input('bpjs_ketenagakerjaan'),
            'bonus'                => $request->input('bonus'),
            'insentif'             => $request->input('insentif'),
            'thr'                  => $request->input('thr'),
            'komisi'               => $request->input('komisi'),
            'tunjangan'            => $request->input('tunjangan'),
            'pph21'                => $request->input('pph21'),
            'kasbon'               => $request->input('kasbon'),
            'potongan_lain'        => $request->input('potongan_lain'),
        ], fn($v) => $v !== null && $v !== '');

        if ($request->input('uang_lembur_manual') === '1') {
            $override['uang_lembur'] = (int) str_replace('.', '', $request->input('uang_lembur', '0'));
        }
        if ($request->input('potongan_alpa_manual') === '1') {
            $override['potongan_absensi'] = (int) str_replace('.', '', $request->input('potongan_absensi', '0'));
        }

        $this->penggajianService->generate($karyawan, $request->periode, $override);

        if ($request->filled('catatan')) {
            $penggajian->refresh()->update(['catatan' => $request->catatan]);
        }

        return redirect()->route('penggajian.show', $penggajian)
            ->with('success', 'Slip gaji berhasil diperbarui.');
    }

    /**
     * AJAX: kembalikan rekap absensi untuk karyawan + periode tertentu
     */
    public function rekapAbsensiAjax(Request $request)
    {
        $request->validate([
            'karyawan_id' => 'required|exists:karyawans,id',
            'periode'     => 'required|date_format:Y-m',
        ]);

        $karyawan = Karyawan::withoutGlobalScope(CabangScope::class)->findOrFail($request->karyawan_id);
        [$tahun, $bulan] = explode('-', $request->periode);
        $rekap = $karyawan->rekapAbsensi((int) $bulan, (int) $tahun);

        $gajiPokok   = (float) $karyawan->gaji_pokok;
        $tarifLembur = $this->penggajianService->hitungTarifLembur($gajiPokok);
        $tarifAlpha  = PengaturanGaji::getSetting('potongan_alpa_per_hari', 0) > 0
            ? PengaturanGaji::getSetting('potongan_alpa_per_hari', 0)
            : ($rekap['hari_kerja'] > 0 ? round($gajiPokok / $rekap['hari_kerja']) : 0);

        return response()->json([
            'success'      => true,
            'rekap'        => $rekap,
            'tarif_lembur' => $tarifLembur,
            'tarif_alpha'  => $tarifAlpha,
        ]);
    }

    /**
     * Hitung preview data tanpa menyimpan
     */
    private function buildPreview(Karyawan $karyawan, string $periode): array
    {
        [$tahun, $bulan] = explode('-', $periode);

        // Gunakan rekapAbsensi() dari model
        $rekap       = $karyawan->rekapAbsensi((int) $bulan, (int) $tahun);
        $hariKerja   = $rekap['hari_kerja'];
        $jumlahHadir = $rekap['hari_hadir'];
        $jumlahAlpha = $rekap['hari_alpha'];
        $jamLembur   = $rekap['total_jam_lembur'];
        $hariTelat   = $rekap['hari_telat'];

        $gajiPokok          = (float) $karyawan->gaji_pokok;
        $tunjanganJabatan   = (float) ($karyawan->tunjangan_jabatan   ?? 0);
        $tunjanganMakan     = (float) ($karyawan->tunjangan_makan     ?? 0);
        $tunjanganTransport = (float) ($karyawan->tunjangan_transport ?? 0);

        $tarifLembur = $this->penggajianService->hitungTarifLembur($gajiPokok);
        $uangLembur  = round($jamLembur * $tarifLembur);

        $tarifAlpha = PengaturanGaji::getSetting('potongan_alpa_per_hari', 0) > 0
            ? PengaturanGaji::getSetting('potongan_alpa_per_hari', 0)
            : ($hariKerja > 0 ? round($gajiPokok / $hariKerja) : 0);
        $potonganAbsensi = $tarifAlpha > 0 ? round($tarifAlpha * $jumlahAlpha) : 0;
        $persenBpjsKes      = (float) ($karyawan->tunjangan_bpjs_kesehatan_persen ?? 1);
        $bpjsKesehatan      = round($gajiPokok * $persenBpjsKes / 100);
        $persenBpjsTk       = (float) ($karyawan->tunjangan_bpjs_tk_persen ?? 2);
        $bpjsTk             = round($gajiPokok * $persenBpjsTk / 100);
        $brutoBulan         = $gajiPokok + $tunjanganJabatan + $tunjanganMakan + $tunjanganTransport + $uangLembur;
        $pph21              = $this->penggajianService->hitungPph21($brutoBulan * 12);

        return [
            'jumlah_hari_kerja'    => $hariKerja,
            'jumlah_hadir'         => $jumlahHadir,
            'jumlah_alpha'         => $jumlahAlpha,
            'jam_lembur_total'     => $jamLembur,
            'jumlah_telat'         => $hariTelat,
            'gaji_pokok'           => $gajiPokok,
            'tunjangan_jabatan'    => $tunjanganJabatan,
            'tunjangan_makan'      => $tunjanganMakan,
            'tunjangan_transport'  => $tunjanganTransport,
            'tunjangan_kehadiran'  => 0,
            'uang_lembur'          => $uangLembur,
            'tarifLembur'          => $tarifLembur,
            'tarifAlpha'           => $tarifAlpha,
            'bonus'                => 0,
            'insentif'             => 0,
            'thr'                  => 0,
            'komisi'               => 0,
            'tunjangan'            => 0,
            'potongan_absensi'     => $potonganAbsensi,
            'bpjs_kesehatan'       => $bpjsKesehatan,
            'bpjs_ketenagakerjaan' => $bpjsTk,
            'pph21'                => $pph21,
            'kasbon'               => 0,
            'potongan_lain'        => 0,
        ];
    }

    /**
     * Hapus slip gaji (hanya draft)
     */
    public function destroy(Penggajian $penggajian)
    {
        if ($penggajian->status !== 'draft') {
            return back()->with('error', 'Hanya slip gaji berstatus draft yang bisa dihapus.');
        }

        $penggajian->delete();

        return redirect()->route('penggajian.index')
            ->with('success', 'Slip gaji berhasil dihapus.');
    }
}
