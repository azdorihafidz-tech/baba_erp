<?php

namespace App\Http\Controllers;

use App\Exports\LaporanBarangKasirExport;
use App\Services\LaporanBarangKasirService;
use App\Support\CabangAktif;
use App\Support\PeriodeLaporan;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\LaporanPenjualanKasirSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 4.46 Tahap 2 — Laporan Penjualan Produk di menu Penjualan (bisa dibuka kasir):
 * qty, penjualan sebelum diskon, diskon, pendapatan per produk (+ rincian
 * varian), grafik 10 teratas. Modal & keuntungan hanya untuk izin
 * penjualan.laporan.keuntungan — tidak dikirim ke view/export kalau tidak berhak.
 */
class LaporanProdukKasirController extends Controller
{
    public const KOLOM_RAHASIA = ['hpp' => 1, 'keuntungan' => 1, 'margin' => 1];

    public function index(Request $request, LaporanBarangKasirService $svc)
    {
        abort_unless(auth()->user()->can('penjualan.laporan_produk.view'), 403);

        $cabangId = CabangAktif::untukLaporan();
        $lihatUntung = auth()->user()->can(LaporanPenjualanKasirSeeder::IZIN_KEUNTUNGAN);
        $periode = PeriodeLaporan::baca($request, $cabangId);

        $kategori = $request->filled('kategori') ? (int) $request->kategori : null;
        $cari = trim((string) $request->cari) ?: null;
        $urutBoleh = $lihatUntung ? ['qty', 'pendapatan', 'keuntungan', 'nama'] : ['qty', 'pendapatan', 'nama'];
        $urut = in_array($request->urut, $urutBoleh, true) ? $request->urut : 'qty';

        $baris = LaporanBarangKasirService::urutkan(
            $svc->produk($periode['dari'], $periode['sampai'], $cabangId, ['kategori' => $kategori, 'cari' => $cari]),
            $urut
        );
        $total = LaporanBarangKasirService::total($baris);
        $adaFilter = $kategori || $cari;
        $biayaTambahan = $adaFilter ? 0.0 : $svc->biayaTambahan($periode['dari'], $periode['sampai'], $cabangId);

        if (! $lihatUntung) {
            [$baris, $total] = self::sembunyikanUntung($baris, $total);
        }

        $daftarKategori = $svc->daftarKategori($periode['dari'], $periode['sampai'], $cabangId);
        $namaKategori = match (true) {
            $kategori === -1 => 'Tanpa kategori',
            (bool) $kategori => $daftarKategori->firstWhere('id', $kategori)?->nama_kategori ?? \App\Models\ItemCategory::find($kategori)?->nama_kategori,
            default => null,
        };
        $cabangNama = $cabangId ? (\App\Models\Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang';

        if (in_array($request->export, ['excel', 'pdf'], true)) {
            $info = ['Periode' => $periode['label'], 'Cabang' => $cabangNama]
                + ($namaKategori ? ['Kategori' => $namaKategori] : [])
                + ($cari ? ['Pencarian' => $cari] : []);

            return self::export($request->export, 'produk', 'Laporan Penjualan Produk', $info, $baris, $total, $biayaTambahan, $lihatUntung);
        }

        return view('penjualan.laporan.produk', compact(
            'periode', 'baris', 'total', 'biayaTambahan', 'adaFilter', 'lihatUntung', 'urut', 'kategori', 'cari',
            'daftarKategori', 'namaKategori', 'cabangNama'
        ) + ['semuaCabang' => $cabangId === null]);
    }

    /** Buang modal/keuntungan dari baris, rincian varian/produk, dan total. */
    public static function sembunyikanUntung(Collection $baris, array $total): array
    {
        $hapus = function ($r) use (&$hapus) {
            $r = array_diff_key($r, self::KOLOM_RAHASIA);
            foreach (['varian', 'produk'] as $anak) {
                if (isset($r[$anak]) && $r[$anak] instanceof Collection) {
                    $r[$anak] = $r[$anak]->map($hapus);
                }
            }

            return $r;
        };

        return [$baris->map($hapus), array_diff_key($total, self::KOLOM_RAHASIA)];
    }

    /** Export Excel/PDF bersama Laporan Produk & Kategori. */
    public static function export(string $format, string $jenis, string $judul, array $info, Collection $baris, array $total, float $biayaTambahan, bool $lihatUntung)
    {
        $nama = str($judul)->slug() . '-' . str($info['Periode'])->slug() . '-' . now()->format('Ymd-His');

        if ($format === 'excel') {
            return Excel::download(
                new LaporanBarangKasirExport($jenis, $judul, $info, $baris, $total, $biayaTambahan, $lihatUntung, auth()->user()->name),
                $nama . '.xlsx'
            );
        }

        return Pdf::loadView('penjualan.laporan.barang-pdf', [
            'judulLaporan'  => $judul,
            'filterInfo'    => $info,
            'jenis'         => $jenis,
            'baris'         => $baris,
            'total'         => $total,
            'biayaTambahan' => $biayaTambahan,
            'lihatUntung'   => $lihatUntung,
        ])->setPaper('a4', $lihatUntung ? 'landscape' : 'portrait')->download($nama . '.pdf');
    }
}
