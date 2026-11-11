<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Eksekutif — Halaman 7 (Evidence-Based Findings). Setiap temuan
 * adalah FAKTA KONKRET dari data real (nominal, nama, tanggal), bukan
 * kalimat generik/template teoritis. REUSE penuh dari BebanBreakdownService
 * (Sesi A) dan PoDashboardService (existing, marathon PO Tools) — hanya 2
 * temuan (Produktivitas Transaksi, Kasir Performance, Trend Kas) yang butuh
 * query baru karena genuinely tidak ada service untuk itu; keduanya query
 * agregat sederhana (join/groupby/sum), bukan duplikasi logic akuntansi.
 */
class EvidenceBasedFindingsService
{
    public function __construct(
        private BebanBreakdownService $bebanBreakdownService,
        private PoDashboardService $poDashboardService,
    ) {
    }

    public function getFindings(Carbon $tanggal, ?int $cabangId = null): array
    {
        $mulaiBulan = $tanggal->copy()->startOfMonth();
        $akhirTanggal = $tanggal->copy()->endOfDay();

        return [
            $this->temuanStrukturBeban($mulaiBulan, $akhirTanggal, $cabangId),
            $this->temuanProduktivitasTransaksi($mulaiBulan, $akhirTanggal, $cabangId),
            $this->temuanHutangJatuhTempo($cabangId),
            $this->temuanKasirPerformance($mulaiBulan, $akhirTanggal, $cabangId),
            $this->temuanTrendKas30Hari($tanggal, $cabangId),
        ];
    }

    /** REUSE BebanBreakdownService — cari kategori beban paling dominan. */
    private function temuanStrukturBeban(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $ringkasan = $this->bebanBreakdownService->getRingkasanSemuaKategori($mulai, $akhir, $cabangId);
        $dominan = collect($ringkasan['kategori'])->sortByDesc('persen_dari_total_beban')->first();

        if (!$dominan) {
            return [
                'judul' => 'Struktur Beban',
                'fakta' => 'Tidak ada beban tercatat pada periode ini.',
                'severity' => 'info',
            ];
        }

        return [
            'judul' => 'Struktur Beban',
            'fakta' => sprintf(
                '"%s" adalah kontributor beban terbesar bulan ini: Rp%s (%s%% dari total beban Rp%s).',
                $dominan['nama'],
                number_format($dominan['jumlah'], 0, ',', '.'),
                number_format($dominan['persen_dari_total_beban'], 1, ',', '.'),
                number_format($ringkasan['total_beban'], 0, ',', '.')
            ),
            'severity' => $dominan['alert'] === 'kritis' ? 'kritis' : ($dominan['alert'] === 'perhatian' ? 'perhatian' : 'info'),
            'link_route' => 'laporan.buku-besar.index',
        ];
    }

    /** Query baru sederhana — count/sum/max/min polos dari Order. */
    private function temuanProduktivitasTransaksi(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $query = Order::withoutGlobalScopes()
            ->penjualanSah()
            ->whereBetween('tanggal_order', [$mulai->toDateString(), $akhir->toDateString()]);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $totalOrder = (clone $query)->count();
        if ($totalOrder === 0) {
            return [
                'judul' => 'Produktivitas Transaksi',
                'fakta' => 'Belum ada transaksi tercatat pada periode ini.',
                'severity' => 'info',
            ];
        }

        $terbesar = (clone $query)->orderByDesc('total_bayar')->first(['nomor_order', 'total_bayar', 'nama_pelanggan']);
        $rataRata = (clone $query)->avg('total_bayar');

        return [
            'judul' => 'Produktivitas Transaksi',
            'fakta' => sprintf(
                '%s order tercatat, rata-rata Rp%s/order. Transaksi terbesar: #%s (%s) senilai Rp%s.',
                number_format($totalOrder, 0, ',', '.'),
                number_format($rataRata, 0, ',', '.'),
                $terbesar->nomor_order,
                $terbesar->nama_pelanggan ?? 'Umum',
                number_format($terbesar->total_bayar, 0, ',', '.')
            ),
            'severity' => 'info',
            'link_route' => 'laporan.penjualan',
        ];
    }

    /** REUSE PoDashboardService — sudah punya umur_hari + badge. */
    private function temuanHutangJatuhTempo(?int $cabangId): array
    {
        $pending = $this->poDashboardService->getPoPendingBayar($cabangId);

        if ($pending->isEmpty()) {
            return [
                'judul' => 'Hutang Jatuh Tempo',
                'fakta' => 'Tidak ada PO yang sudah diterima namun belum dibayar.',
                'severity' => 'positif',
            ];
        }

        $totalHutang = $pending->sum('total_harga');
        $tertua = $pending->sortByDesc('umur_hari')->first();

        return [
            'judul' => 'Hutang Jatuh Tempo',
            'fakta' => sprintf(
                '%s PO diterima belum dibayar, total Rp%s. Paling lama: PO #%s dari %s, sudah %s hari.',
                $pending->count(),
                number_format($totalHutang, 0, ',', '.'),
                $tertua->nomor_po,
                $tertua->nama_supplier ?? '-',
                $tertua->umur_hari
            ),
            'severity' => $tertua->umur_hari > 7 ? 'kritis' : ($tertua->umur_hari >= 4 ? 'perhatian' : 'info'),
            'link_route' => 'pembelian.po-dashboard.index',
        ];
    }

    /** Query baru sederhana — join+groupby polos, sama pola dgn LaporanPenjualanController (tidak reuse controller, cukup mirror query-nya). */
    private function temuanKasirPerformance(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $query = DB::table('orders')
            ->join('users', 'orders.kasir_id', '=', 'users.id')
            ->whereNull('orders.deleted_at')
            ->whereNotIn('orders.status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('orders.deleted_at')
            ->whereBetween('orders.tanggal_order', [$mulai->toDateString(), $akhir->toDateString()]);
        if ($cabangId) {
            $query->where('orders.cabang_id', $cabangId);
        }

        $ranking = $query->selectRaw('orders.kasir_id, users.name as nama_kasir, COUNT(orders.id) as jumlah_order, SUM(orders.total_bayar) as total_omzet')
            ->groupBy('orders.kasir_id', 'users.name')
            ->orderByDesc('total_omzet')
            ->get();

        if ($ranking->isEmpty()) {
            return [
                'judul' => 'Kasir Performance',
                'fakta' => 'Belum ada transaksi kasir tercatat pada periode ini.',
                'severity' => 'info',
            ];
        }

        $top = $ranking->first();
        $fakta = sprintf(
            'Kasir dengan omzet tertinggi: %s (Rp%s dari %s order).',
            $top->nama_kasir,
            number_format($top->total_omzet, 0, ',', '.'),
            number_format($top->jumlah_order, 0, ',', '.')
        );

        if ($ranking->count() > 1) {
            $terendah = $ranking->last();
            $fakta .= sprintf(
                ' Terendah: %s (Rp%s dari %s order).',
                $terendah->nama_kasir,
                number_format($terendah->total_omzet, 0, ',', '.'),
                number_format($terendah->jumlah_order, 0, ',', '.')
            );
        }

        return [
            'judul' => 'Kasir Performance',
            'fakta' => $fakta,
            'severity' => 'info',
            'link_route' => 'laporan.penjualan',
        ];
    }

    /** Query baru sederhana — reuse pola query dari BusinessOverviewService::getArusKasSummary(), window 30 hari rolling. */
    private function temuanTrendKas30Hari(Carbon $tanggal, ?int $cabangId): array
    {
        $mulai30Hari = $tanggal->copy()->subDays(30);

        $query = TransaksiKeuangan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->whereBetween('tanggal_transaksi', [$mulai30Hari->toDateString(), $tanggal->toDateString()])
            ->whereNull('deleted_at');
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $totalMasuk = (float) (clone $query)->where('tipe', \App\Enums\TipeTransaksiKeuangan::Pemasukan)->sum('jumlah');
        $totalKeluar = (float) (clone $query)->where('tipe', \App\Enums\TipeTransaksiKeuangan::Pengeluaran)->sum('jumlah');

        $terbesar = (clone $query)
            ->where('tipe', \App\Enums\TipeTransaksiKeuangan::Pengeluaran)
            ->orderByDesc('jumlah')
            ->first(['keterangan', 'jumlah', 'tanggal_transaksi']);

        $fakta = sprintf(
            '30 hari terakhir: kas masuk Rp%s, kas keluar Rp%s (net Rp%s).',
            number_format($totalMasuk, 0, ',', '.'),
            number_format($totalKeluar, 0, ',', '.'),
            number_format($totalMasuk - $totalKeluar, 0, ',', '.')
        );
        if ($terbesar) {
            $fakta .= sprintf(
                ' Pengeluaran tunggal terbesar: "%s" Rp%s pada %s.',
                $terbesar->keterangan ?? '-',
                number_format($terbesar->jumlah, 0, ',', '.'),
                Carbon::parse($terbesar->tanggal_transaksi)->format('d/m/Y')
            );
        }

        return [
            'judul' => 'Trend Kas 30 Hari',
            'fakta' => $fakta,
            'severity' => ($totalMasuk - $totalKeluar) < 0 ? 'perhatian' : 'positif',
            'link_route' => 'laporan.keuangan.arus-kas',
        ];
    }
}
