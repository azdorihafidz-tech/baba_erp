<?php

namespace App\Services;

use App\Enums\StatusOrder;
use App\Models\ChartOfAccount;
use App\Models\KategoriTransaksi as KategoriTransaksiModel;
use App\Models\OrderItem;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;

/**
 * Laporan Laba Rugi Formal berstandar SAK ETAP — dikelompokkan per kode akun
 * COA (bukan kategori simple seperti Laporan Laba Rugi existing/lama, yang
 * TIDAK disentuh sama sekali oleh service ini). Murni READ, tidak menulis
 * data manapun.
 *
 * TEMUAN AUDIT PENTING: TransaksiKeuangan hasil order/POS (referensi_type=
 * 'order') TIDAK PERNAH punya kategori_id terisi — PenjualanService::
 * buatOrder() (yang tidak boleh disentuh) cuma mengisi kolom enum lama
 * `kategori`. Supaya pendapatan jasa giling/penjualan produk tidak hilang
 * dari laporan, resolusi kode_akun_coa pakai fallback: PRIORITAS
 * kategori_id->kode_akun_coa, FALLBACK ke KategoriTransaksi::
 * kodeAkunCoaFromEnum($kategoriEnum) kalau kategori_id NULL atau kategori
 * tersebut belum di-mapping. Resolusi ini MURNI di level query laporan,
 * tidak pernah menulis balik ke tabel manapun.
 *
 * HPP (temuan audit kesehatan data 2026-08-07): SEBELUMNYA HPP diambil dari
 * kategori transaksi `PBB` (Pembelian Bahan Baku) — ini mengukur BELANJA
 * bahan baku bulan itu, BUKAN biaya bahan yang benar-benar terpakai untuk
 * order yang terjual (bisa Rp0 di bulan tanpa belanja padahal ada penjualan,
 * atau overstate drastis di bulan stock-up). Sekarang HPP dihitung dari
 * SUM(order_items.hpp) — FIFO cost aktual, pola yang SAMA persis dengan
 * BepOtomatisService & LaporanKonsumsiBahanService (sudah terbukti akurat).
 * order_items.hpp TIDAK PERNAH tercatat sebagai baris transaksi_keuangans —
 * karena itu BukuBesarService (akun 5-xxxx, sumbernya transaksi_keuangans)
 * TIDAK BISA ikut merefleksikan angka ini dan TETAP menampilkan basis PBB
 * lama (Buku Besar tetap valid sebagai ledger pembelian, cuma beda konsep
 * dari "HPP" di laporan ini). CrossCheckValidator::cekTotalBeban()
 * disesuaikan mengecualikan HPP dari perbandingan supaya tidak jadi
 * false-alarm permanen — lihat catatan di file itu.
 */
class LabaRugiFormalService
{
    public function hitungLabaRugi(Carbon $mulai, Carbon $akhir, ?int $cabangId = null): array
    {
        $saldoPerKode = $this->hitungSaldoPerKodeAkun($mulai, $akhir, $cabangId);

        $pendapatan = $this->breakdownTipe('pendapatan', $saldoPerKode);
        $hpp = $this->breakdownHpp($mulai, $akhir, $cabangId);
        $bebanOperasional = $this->breakdownTipe('beban_operasional', $saldoPerKode);
        $pendapatanLain = $this->breakdownTipe('pendapatan_lain', $saldoPerKode);
        $bebanLain = $this->breakdownTipe('beban_lain', $saldoPerKode);

        $totalPendapatan = array_sum(array_column($pendapatan, 'jumlah'));
        $totalHpp = array_sum(array_column($hpp, 'jumlah'));
        $labaKotor = $totalPendapatan - $totalHpp;

        $totalBebanOperasional = array_sum(array_column($bebanOperasional, 'jumlah'));
        $labaUsaha = $labaKotor - $totalBebanOperasional;

        $totalPendapatanLain = array_sum(array_column($pendapatanLain, 'jumlah'));
        $totalBebanLain = array_sum(array_column($bebanLain, 'jumlah'));

        $labaBersihSebelumPajak = $labaUsaha + $totalPendapatanLain - $totalBebanLain;

        // Tidak ada fitur pencatatan pajak penghasilan usaha saat ini
        $pajakPenghasilan = 0.0;
        $labaBersihSetelahPajak = $labaBersihSebelumPajak - $pajakPenghasilan;

        return [
            'periode' => ['mulai' => $mulai->toDateString(), 'akhir' => $akhir->toDateString()],
            'pendapatan' => ['detail' => $pendapatan, 'total' => $totalPendapatan],
            'hpp' => ['detail' => $hpp, 'total' => $totalHpp],
            'laba_kotor' => $labaKotor,
            'beban_operasional' => ['detail' => $bebanOperasional, 'total' => $totalBebanOperasional],
            'laba_usaha' => $labaUsaha,
            'pendapatan_lain' => ['detail' => $pendapatanLain, 'total' => $totalPendapatanLain],
            'beban_lain' => ['detail' => $bebanLain, 'total' => $totalBebanLain],
            'laba_bersih_sebelum_pajak' => $labaBersihSebelumPajak,
            'pajak_penghasilan' => $pajakPenghasilan,
            'laba_bersih_setelah_pajak' => $labaBersihSetelahPajak,
        ];
    }

    /**
     * Return array [kode_akun_coa => total_jumlah] untuk periode+cabang
     * tertentu, dengan resolusi fallback enum->kode untuk baris tanpa
     * kategori_id (lihat catatan class-level).
     */
    private function hitungSaldoPerKodeAkun(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $query = TransaksiKeuangan::query()
            ->leftJoin('kategori_transaksis', 'kategori_transaksis.id', '=', 'transaksi_keuangans.kategori_id')
            ->whereBetween('transaksi_keuangans.tanggal_transaksi', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('transaksi_keuangans.deleted_at');

        \App\Support\KeuanganBaba::saring($query, 'transaksi_keuangans.cabang_id', $cabangId); // Tahap 4: tanpa toko mitra

        $rows = $query->selectRaw('
                transaksi_keuangans.kategori_id as kategori_id,
                kategori_transaksis.kode_akun_coa as kode_akun_coa,
                kategori_transaksis.tipe as kategori_tipe,
                transaksi_keuangans.kategori as kategori_enum,
                transaksi_keuangans.tipe as tipe_transaksi,
                SUM(transaksi_keuangans.jumlah) as total
            ')
            ->groupBy(
                'transaksi_keuangans.kategori_id',
                'kategori_transaksis.kode_akun_coa',
                'kategori_transaksis.tipe',
                'transaksi_keuangans.kategori',
                'transaksi_keuangans.tipe'
            )
            ->get();

        // Catatan teknis: alias kolom SENGAJA dibuat beda nama dari kolom asli
        // model (mis. `tipe_transaksi` bukan `tipe`, `kategori_enum` bukan
        // `kategori`) — kalau nama alias sama persis dengan nama kolom asli,
        // Eloquent tetap menerapkan aturan cast model TransaksiKeuangan
        // (tipe->TipeTransaksiKeuangan enum, kategori->KategoriTransaksi enum)
        // ke hasil selectRaw ini walau nilainya cuma string biasa dari SQL —
        // perbandingan string biasa (=== 'pemasukan') jadi selalu false tanpa
        // pernah error (bug nyata yang ditemukan & diperbaiki saat testing).
        $saldoPerKode = [];
        foreach ($rows as $row) {
            $kode = $this->resolveKodeAkun(
                $row->kategori_id,
                $row->kode_akun_coa,
                $row->kategori_tipe,
                $row->kategori_enum,
                $row->tipe_transaksi
            );

            if (!$kode) {
                continue; // transfer internal / saldo awal — bukan event akuntansi riil
            }
            $saldoPerKode[$kode] = ($saldoPerKode[$kode] ?? 0) + (float) $row->total;
        }

        return $saldoPerKode;
    }

    /**
     * Resolusi kode akun COA untuk SATU baris TransaksiKeuangan — dipakai
     * bareng oleh hitungSaldoPerKodeAkun() (agregat) dan BukuBesarService
     * (per-baris) supaya logika resolusi TIDAK PERNAH diduplikasi/ditulis
     * ulang di 2 tempat (lihat CLAUDE.md Rule #47). Public (bukan private)
     * justru supaya reusable lintas service.
     *
     * Resolusi berlapis 3 tingkat (detail lengkap di catatan class-level):
     * 1. kategori_id terisi & kode_akun_coa ADA → pakai apa adanya
     * 2. kategori_id terisi tapi kode_akun_coa sengaja NULL (transfer
     *    internal) → return null, TIDAK di-fallback ke enum
     * 3. kategori_id NULL sama sekali (order/POS) → fallback ke enum lama
     */
    public function resolveKodeAkun(
        ?int $kategoriId,
        ?string $kodeAkunCoaKategori,
        ?string $kategoriTipe,
        ?string $kategoriEnum,
        ?string $tipeTransaksi
    ): ?string {
        if ($kategoriId !== null) {
            // kategori_id terisi — HORMATI apa adanya, termasuk kalau
            // kode_akun_coa-nya sengaja NULL (SETOR-IN/SETOR-OUT/SALDO =
            // transfer internal, BUKAN event akuntansi riil). Tidak boleh
            // di-fallback ke enum di sini, atau transfer internal akan
            // salah ke-reklas jadi Beban Lain-lain via enum 'lainnya'.
            $kode = $kodeAkunCoaKategori;

            // Kategori dual-purpose (tipe='keduanya', mis. "Lainnya"/
            // "Marketing") cuma punya 1 kode_akun_coa default (ke sisi
            // beban) — kalau transaksi aktualnya PEMASUKAN, override ke
            // Pendapatan Usaha Lainnya (4-1199) supaya tidak salah masuk
            // sisi Beban Operasional.
            if ($kategoriTipe === 'keduanya' && $tipeTransaksi === 'pemasukan') {
                $kode = '4-1199';
            }

            return $kode;
        }

        // kategori_id NULL sama sekali (mis. TransaksiKeuangan hasil
        // order/POS — PenjualanService tidak pernah mengisi kategori_id)
        // — fallback ke mapping enum lama.
        return KategoriTransaksiModel::kodeAkunCoaFromEnum($kategoriEnum);
    }

    /**
     * Breakdown per akun leaf untuk 1 tipe COA — SEMUA leaf akun ditampilkan
     * (termasuk yang belum pernah dipakai, jumlah 0) supaya struktur laporan
     * selalu lengkap & konsisten dengan Chart of Accounts, bukan cuma akun
     * yang kebetulan sudah ada transaksinya.
     */
    private function breakdownTipe(string $tipe, array $saldoPerKode): array
    {
        return ChartOfAccount::where('tipe', $tipe)
            ->where('is_leaf', true)
            ->orderBy('kode')
            ->get()
            ->map(fn ($akun) => [
                'kode' => $akun->kode,
                'nama' => $akun->nama,
                'jumlah' => (float) ($saldoPerKode[$akun->kode] ?? 0),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Breakdown HPP — SUMBER BEDA dari breakdownTipe() lain (lihat catatan
     * class-level): SUM(order_items.hpp), bukan transaksi_keuangans. Tetap
     * menampilkan SEMUA leaf akun tipe='hpp' (struktur konsisten dengan
     * breakdown lain), tapi seluruh nilai dikaitkan ke akun 5-1101 (HPP
     * Bahan Baku) — satu-satunya angka yang tersedia dari order_items.hpp
     * adalah total FIFO cost, tidak ada pemecahan bahan-baku vs bumbu vs
     * tenaga-kerja di levelnya, jadi 5-1102/5-1103 tetap Rp0 (sama seperti
     * sebelumnya — kedua akun itu memang belum pernah punya sumber data).
     */
    private function breakdownHpp(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        // ERP BABA Tahap 4: + HPP Pesanan Online gudang (barang keluar − barang retur kembali).
        $totalHpp = $this->hitungHppDariOrderItems($mulai, $akhir, $cabangId)
            + app(\App\Services\JurnalOnlineService::class)->hpp($mulai, $akhir, $cabangId);

        return ChartOfAccount::where('tipe', 'hpp')
            ->where('is_leaf', true)
            ->orderBy('kode')
            ->get()
            ->map(fn ($akun) => [
                'kode' => $akun->kode,
                'nama' => $akun->nama,
                'jumlah' => $akun->kode === '5-1101' ? $totalHpp : 0.0,
            ])
            ->values()
            ->toArray();
    }

    /**
     * SUM(order_items.hpp) — FIFO cost aktual bahan yang terpakai untuk
     * order yang benar-benar terjual, pola query SAMA PERSIS dengan
     * BepOtomatisService::hitungDataJasaGiling() dan
     * LaporanKonsumsiBahanService (sudah terbukti akurat) — bedanya di sini
     * TIDAK difilter tipe_order (Laba Rugi harus mencakup HPP semua lini
     * bisnis, bukan cuma jasa giling), mengikuti scope LaporanKonsumsiBahan.
     * Filter status != Dibatalkan (bukan literal ='selesai') supaya
     * konsisten dengan kedua service itu DAN dengan sisi pendapatan
     * (order_items/orders yang sudah dipakai di breakdownTipe('pendapatan')
     * via fallback enum turut mencakup order non-dibatalkan).
     */
    private function hitungHppDariOrderItems(Carbon $mulai, Carbon $akhir, ?int $cabangId): float
    {
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotIn('orders.status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('orders.deleted_at')
            ->whereBetween('orders.tanggal_order', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('orders.deleted_at')
            ->whereNull('order_items.deleted_at');

        \App\Support\KeuanganBaba::saring($query, 'orders.cabang_id', $cabangId);

        return (float) $query->sum('order_items.hpp');
    }
}
