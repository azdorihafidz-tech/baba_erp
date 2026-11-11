<?php

namespace App\Http\Controllers;

use App\Services\LaporanBarangKasirService;
use App\Support\CabangAktif;
use App\Support\PeriodeLaporan;
use Database\Seeders\LaporanPenjualanKasirSeeder;
use Illuminate\Http\Request;

/**
 * 4.46 Tahap 3 — Laporan Penjualan Kategori di menu Penjualan (bisa dibuka
 * kasir): qty, penjualan, diskon, pendapatan per kategori; klik kategori untuk
 * melihat produk di dalamnya. Angka dihitung dari Laporan Produk yang sama.
 * Modal & keuntungan hanya untuk izin penjualan.laporan.keuntungan.
 */
class LaporanKategoriKasirController extends Controller
{
    public function index(Request $request, LaporanBarangKasirService $svc)
    {
        abort_unless(auth()->user()->can('penjualan.laporan_kategori.view'), 403);

        $cabangId = CabangAktif::untukLaporan();
        $lihatUntung = auth()->user()->can(LaporanPenjualanKasirSeeder::IZIN_KEUNTUNGAN);
        $periode = PeriodeLaporan::baca($request, $cabangId);

        $urutBoleh = $lihatUntung ? ['qty', 'pendapatan', 'keuntungan', 'nama'] : ['qty', 'pendapatan', 'nama'];
        $urut = in_array($request->urut, $urutBoleh, true) ? $request->urut : 'pendapatan';

        $baris = LaporanBarangKasirService::urutkan($svc->kategori($periode['dari'], $periode['sampai'], $cabangId), $urut, 'kategori');
        $total = LaporanBarangKasirService::total($baris);
        $biayaTambahan = $svc->biayaTambahan($periode['dari'], $periode['sampai'], $cabangId);

        if (! $lihatUntung) {
            [$baris, $total] = LaporanProdukKasirController::sembunyikanUntung($baris, $total);
        }

        $cabangNama = $cabangId ? (\App\Models\Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang';

        if (in_array($request->export, ['excel', 'pdf'], true)) {
            return LaporanProdukKasirController::export($request->export, 'kategori', 'Laporan Penjualan Kategori',
                ['Periode' => $periode['label'], 'Cabang' => $cabangNama], $baris, $total, $biayaTambahan, $lihatUntung);
        }

        return view('penjualan.laporan.kategori', compact('periode', 'baris', 'total', 'biayaTambahan', 'lihatUntung', 'urut', 'cabangNama')
            + ['semuaCabang' => $cabangId === null, 'bolehLihatProduk' => auth()->user()->can('penjualan.laporan_produk.view')]);
    }
}
