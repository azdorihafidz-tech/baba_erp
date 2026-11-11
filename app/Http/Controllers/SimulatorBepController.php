<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Services\BepOtomatisService;
use App\Services\NeracaService;
use App\Http\Requests\SimulatorBepSnapshotRequest;
use App\Exports\LaporanSimulatorBepExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Menu Interactive Simulator BEP — slider input real-time (volume, harga
 * jual, biaya variabel, beban tetap) untuk eksperimen Owner. Kalkulasi BEP
 * dijalankan di client-side JavaScript (aljabar dasar, bukan data/logic
 * otoritatif) — nilai AWAL slider REUSE dari BepOtomatisService/NeracaService
 * (bulan berjalan), tidak ada query/kalkulasi akuntansi baru di controller.
 */
class SimulatorBepController extends Controller
{
    public function __construct(
        private BepOtomatisService $bepOtomatisService,
        private NeracaService $neracaService,
    ) {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.simulator.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->cabangSaja()->get();

        $cabangId = $request->filled('cabang_id') ? (int) $request->cabang_id : null;
        if (!$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $tanggal = Carbon::now();
        $mulaiBulan = $tanggal->copy()->startOfMonth();

        $bep = $this->bepOtomatisService->hitungBepOtomatis($mulaiBulan, $tanggal->copy()->endOfDay(), $cabangId);
        $neraca = $this->neracaService->hitungNeraca($tanggal, $cabangId);

        $defaultValues = [
            'volume_harian_kg' => round($bep['volume_aktual'] / max(1, $mulaiBulan->diffInDays($tanggal) + 1), 2),
            'harga_jual_per_kg' => round($bep['harga_jual_per_unit'], 2),
            'biaya_variabel_per_kg' => round($bep['biaya_variabel_per_unit'], 2),
            'biaya_tetap_bulanan' => round($bep['biaya_tetap'], 0),
            'modal_awal' => round($neraca['modal']['modal_owner'], 0),
        ];

        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        return view('laporan.simulator-bep.index', compact('cabangs', 'cabangId', 'cabangNama', 'defaultValues'));
    }

    private const HARI_KERJA = 26;
    private const SKENARIO_PERSEN = [-10, -5, 0, 5, 10];

    /**
     * Reproduce PERSIS logic `hitung()` di index.blade.php (JS) — dihitung
     * ULANG di server dari payload mentah supaya snapshot tidak bisa
     * dipalsukan/rusak lewat manipulasi JS browser.
     */
    private function buildSnapshot(array $data): array
    {
        $namaSimulasi = trim((string) ($data['nama_simulasi'] ?? '')) !== ''
            ? $data['nama_simulasi']
            : 'Simulasi BEP - ' . now()->translatedFormat('d F Y');

        $cabangId = $data['cabang_id'] ?? null;
        $cabangNama = $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang (Konsolidasi)';

        $volumeHarian = (float) $data['volume_harian'];
        $hargaJual = (float) $data['harga_jual'];
        $biayaVariabel = (float) $data['biaya_variabel'];
        $bebanTetap = (float) $data['beban_tetap'];
        $modalAwal = (float) ($data['modal_awal'] ?? 0);
        $targetProfit = isset($data['target_profit']) && $data['target_profit'] !== null && $data['target_profit'] !== ''
            ? (float) $data['target_profit']
            : null;

        $margin = $hargaJual - $biayaVariabel;
        $feasible = $margin > 0;

        $bepUnit = null;
        $bepRupiah = null;
        $marginRatio = null;
        $volumeBulanan = $volumeHarian * self::HARI_KERJA;
        $omzetBulanan = null;
        $labaBulanan = null;
        $marginOfSafetyPersen = null;

        if ($feasible) {
            $bepUnit = $bebanTetap / $margin;
            $bepRupiah = $bepUnit * $hargaJual;
            $marginRatio = $hargaJual > 0 ? ($margin / $hargaJual) * 100 : null;
            $omzetBulanan = $volumeBulanan * $hargaJual;
            $hppBulanan = $volumeBulanan * $biayaVariabel;
            $labaBulanan = $omzetBulanan - $hppBulanan - $bebanTetap;

            if ($targetProfit !== null && $volumeBulanan > 0) {
                $marginOfSafetyPersen = (($volumeBulanan - $bepUnit) / $volumeBulanan) * 100;
            }
        }

        $kesimpulan = $this->buildKesimpulan($feasible, $bepUnit, $volumeBulanan, $labaBulanan, $modalAwal, $targetProfit, $marginOfSafetyPersen);

        $sensitivitas = $this->buildSensitivitas($hargaJual, $biayaVariabel, $bebanTetap);

        return compact(
            'namaSimulasi', 'cabangNama', 'cabangId',
            'volumeHarian', 'hargaJual', 'biayaVariabel', 'bebanTetap', 'modalAwal', 'targetProfit',
            'feasible', 'margin', 'bepUnit', 'bepRupiah', 'marginRatio',
            'volumeBulanan', 'omzetBulanan', 'labaBulanan', 'marginOfSafetyPersen',
            'kesimpulan', 'sensitivitas'
        );
    }

    private function buildKesimpulan(bool $feasible, ?float $bepUnit, float $volumeBulanan, ?float $labaBulanan, float $modalAwal, ?float $targetProfit, ?float $marginOfSafetyPersen): array
    {
        if (!$feasible) {
            return ['Margin kontribusi negatif/nol — BEP tidak bisa dicapai pada kombinasi harga/biaya ini.'];
        }

        $baris = [];

        if ($bepUnit > $volumeBulanan) {
            $baris[] = '⚠️ Volume saat ini BELUM mencapai BEP. Volume bulanan ' . number_format($volumeBulanan, 2, ',', '.') . ' kg masih di bawah BEP ' . number_format($bepUnit, 2, ',', '.') . ' kg.';
        } else {
            $baris[] = '✅ Volume saat ini SUDAH mencapai BEP. Volume bulanan ' . number_format($volumeBulanan, 2, ',', '.') . ' kg sudah di atas BEP ' . number_format($bepUnit, 2, ',', '.') . ' kg.';
        }

        $epsilon = 1000;
        if ($labaBulanan > $epsilon && $modalAwal > 0) {
            $baris[] = 'Proyeksi balik modal dalam ~' . number_format($modalAwal / $labaBulanan, 1, ',', '.') . ' bulan (modal awal Rp ' . number_format($modalAwal, 0, ',', '.') . ' ÷ laba bulanan Rp ' . number_format($labaBulanan, 0, ',', '.') . ').';
        } elseif ($labaBulanan < -$epsilon && $modalAwal > 0) {
            $baris[] = 'Proyeksi modal habis dalam ~' . number_format($modalAwal / abs($labaBulanan), 1, ',', '.') . ' bulan jika kondisi rugi ini berlanjut.';
        } else {
            $baris[] = 'Kondisi impas (laba mendekati Rp0).';
        }

        if ($targetProfit !== null && $marginOfSafetyPersen !== null) {
            $baris[] = 'Untuk target profit Rp ' . number_format($targetProfit, 0, ',', '.') . ', margin of safety saat ini ' . number_format($marginOfSafetyPersen, 1, ',', '.') . '%.';
        }

        return $baris;
    }

    private function buildSensitivitas(float $hargaJual, float $biayaVariabel, float $bebanTetap): array
    {
        $matriks = [];

        foreach (self::SKENARIO_PERSEN as $biayaPct) {
            $newBiaya = $biayaVariabel * (1 + $biayaPct / 100);
            $baris = ['biaya_persen' => $biayaPct, 'kolom' => []];

            foreach (self::SKENARIO_PERSEN as $hargaPct) {
                $newHarga = $hargaJual * (1 + $hargaPct / 100);
                $newMargin = $newHarga - $newBiaya;
                $baris['kolom'][$hargaPct] = $newMargin > 0 ? ($bebanTetap / $newMargin) : null;
            }

            $matriks[] = $baris;
        }

        return $matriks;
    }

    public function exportExcel(SimulatorBepSnapshotRequest $request)
    {
        $snapshot = $this->buildSnapshot($request->validated());

        $filename = 'Simulasi-BEP-' . Str::slug($snapshot['namaSimulasi']) . '-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new LaporanSimulatorBepExport($snapshot, auth()->user()->name), $filename);
    }

    public function exportPdf(SimulatorBepSnapshotRequest $request)
    {
        $snapshot = $this->buildSnapshot($request->validated());

        $filename = 'Simulasi-BEP-' . Str::slug($snapshot['namaSimulasi']) . '-' . now()->format('Ymd') . '.pdf';

        $filterInfo = [
            'Nama Simulasi' => $snapshot['namaSimulasi'],
            'Tanggal Simulasi' => now()->translatedFormat('d F Y'),
            'Cabang' => $snapshot['cabangNama'],
        ];

        $pdf = Pdf::loadView('laporan.pdf.simulator-bep', array_merge($snapshot, [
            'judulLaporan' => 'Simulator BEP — Snapshot',
            'filterInfo' => $filterInfo,
            'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
        ]))->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }
}
