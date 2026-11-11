<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fase 5 — Modul Perlengkapan (Rule #66). Service BARU, murni READ dari
 * tabel pemakaian_perlengkapans (bukan reuse LaporanKonsumsiBahanService,
 * yang sumber datanya order_items — perlengkapan tidak pernah masuk order
 * pelanggan, jadi tidak ada baris untuk dibaca di situ).
 */
class LaporanPerlengkapanService
{
    private function baseQuery(Carbon $mulai, Carbon $akhir, ?int $cabangId)
    {
        $query = DB::table('pemakaian_perlengkapans')
            ->join('items', 'items.id', '=', 'pemakaian_perlengkapans.item_id')
            ->whereNull('pemakaian_perlengkapans.deleted_at')
            ->whereBetween('pemakaian_perlengkapans.tanggal_pemakaian', [$mulai->toDateString(), $akhir->toDateString()]);

        if ($cabangId) {
            $query->where('pemakaian_perlengkapans.cabang_id', $cabangId);
        }

        return $query;
    }

    public function getRingkasan(Carbon $mulai, Carbon $akhir, ?int $cabangId): array
    {
        $row = $this->baseQuery($mulai, $akhir, $cabangId)
            ->selectRaw('COUNT(*) as jumlah_kejadian, SUM(pemakaian_perlengkapans.nilai) as total_nilai, COUNT(DISTINCT pemakaian_perlengkapans.item_id) as jumlah_item')
            ->first();

        return [
            'jumlah_kejadian' => (int) ($row->jumlah_kejadian ?? 0),
            'total_nilai'     => (float) ($row->total_nilai ?? 0),
            'jumlah_item'     => (int) ($row->jumlah_item ?? 0),
        ];
    }

    public function getBreakdownPerItem(Carbon $mulai, Carbon $akhir, ?int $cabangId)
    {
        return $this->baseQuery($mulai, $akhir, $cabangId)
            ->selectRaw('
                items.id as item_id,
                items.nama_item,
                items.satuan,
                SUM(pemakaian_perlengkapans.qty) as total_qty,
                SUM(pemakaian_perlengkapans.nilai) as total_nilai,
                COUNT(*) as jumlah_kejadian
            ')
            ->groupBy('items.id', 'items.nama_item', 'items.satuan')
            ->orderByDesc('total_nilai')
            ->get();
    }

    public function getDetailPemakaian(Carbon $mulai, Carbon $akhir, ?int $cabangId)
    {
        return $this->baseQuery($mulai, $akhir, $cabangId)
            ->join('cabangs', 'cabangs.id', '=', 'pemakaian_perlengkapans.cabang_id')
            ->selectRaw('
                pemakaian_perlengkapans.id,
                pemakaian_perlengkapans.tanggal_pemakaian,
                items.nama_item,
                items.satuan,
                cabangs.nama_cabang,
                pemakaian_perlengkapans.qty,
                pemakaian_perlengkapans.nilai,
                pemakaian_perlengkapans.keterangan
            ')
            ->orderByDesc('pemakaian_perlengkapans.tanggal_pemakaian')
            ->orderByDesc('pemakaian_perlengkapans.id')
            ->get();
    }

    /**
     * Tren 6 bulan terakhir (pola sama LaporanKonsumsiBahanService::getTrendBulanan()
     * — subMonths() WAJIB dari startOfMonth() supaya tidak overflow, Rule #30).
     */
    public function getTrendBulanan(?int $cabangId, ?Carbon $end = null): array
    {
        $end = $end ?? Carbon::now();
        $hasil = [];

        for ($i = 5; $i >= 0; $i--) {
            $bulan = $end->copy()->startOfMonth()->subMonths($i);
            $mulaiBulan = $bulan->copy()->startOfMonth();
            $akhirBulan = $bulan->copy()->endOfMonth();

            $row = $this->baseQuery($mulaiBulan, $akhirBulan, $cabangId)
                ->selectRaw('SUM(pemakaian_perlengkapans.nilai) as total_nilai')
                ->first();

            $hasil[] = [
                'periode'     => $bulan->format('Y-m'),
                'label'       => $bulan->translatedFormat('M Y'),
                'total_nilai' => (float) ($row->total_nilai ?? 0),
            ];
        }

        return $hasil;
    }
}
