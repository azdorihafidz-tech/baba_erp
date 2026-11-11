<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Buku Besar (General Ledger) — daftar transaksi per akun COA + saldo
 * berjalan (running balance). Murni READ dari transaksi_keuangans, TIDAK
 * pernah menulis apapun.
 *
 * SCOPE TERBATAS (disetujui Owner saat audit Fase 3): hanya akun bertipe
 * pendapatan/hpp/beban_operasional/pendapatan_lain/beban_lain — akun ini
 * PUNYA jalur resolusi per-transaksi yang jelas dari transaksi_keuangans
 * (lihat LabaRugiFormalService::resolveKodeAkun(), method yang di-reuse
 * PERSIS di sini, bukan ditulis ulang — Rule bisnis #47). Akun Aset/
 * Kewajiban/Modal TIDAK didukung — datanya berasal dari tabel lain (Kas,
 * StockBatch, Asset, PurchaseOrder), bukan dari transaksi_keuangans satu
 * per satu, jadi tidak ada jalur "per transaksi" yang bisa ditampilkan.
 *
 * KETERBATASAN SALDO AWAL: saldo di baris pertama periode SELALU dianggap
 * Rp 0 (bukan saldo kumulatif riil sejak akun itu ada) — sama seperti
 * Neraca yang punya keterbatasan serupa untuk data historis. Ini WAJIB
 * didisclose jelas di UI, bukan disembunyikan.
 */
class BukuBesarService
{
    /** Tipe akun yang didukung Buku Besar — HANYA sisi Laba Rugi. */
    public const TIPE_DIDUKUNG = ['pendapatan', 'hpp', 'beban_operasional', 'pendapatan_lain', 'beban_lain'];

    public function __construct(private LabaRugiFormalService $labaRugiService)
    {
    }

    public function getAkunTersedia(): Collection
    {
        return ChartOfAccount::whereIn('tipe', self::TIPE_DIDUKUNG)
            ->where('is_leaf', true)
            ->orderBy('kode')
            ->get(['kode', 'nama', 'tipe', 'saldo_normal']);
    }

    public function getTransaksiPerAkun(string $kodeAkun, Carbon $mulai, Carbon $akhir, ?int $cabangId = null): array
    {
        $akun = ChartOfAccount::where('kode', $kodeAkun)
            ->whereIn('tipe', self::TIPE_DIDUKUNG)
            ->firstOrFail();

        $query = TransaksiKeuangan::query()
            ->leftJoin('kategori_transaksis', 'kategori_transaksis.id', '=', 'transaksi_keuangans.kategori_id')
            ->whereBetween('transaksi_keuangans.tanggal_transaksi', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereNull('transaksi_keuangans.deleted_at');

        if ($cabangId) {
            $query->where('transaksi_keuangans.cabang_id', $cabangId);
        }

        $rows = $query->select(
                'transaksi_keuangans.id',
                'transaksi_keuangans.tanggal_transaksi',
                'transaksi_keuangans.keterangan',
                'transaksi_keuangans.jumlah',
                'transaksi_keuangans.kategori_id',
                'kategori_transaksis.kode_akun_coa',
                'kategori_transaksis.tipe as kategori_tipe',
                'transaksi_keuangans.kategori as kategori_enum',
                'transaksi_keuangans.tipe as tipe_transaksi'
            )
            ->orderBy('transaksi_keuangans.tanggal_transaksi')
            ->orderBy('transaksi_keuangans.id')
            ->get();

        $isDebetNormal = $akun->saldo_normal === 'debet';
        $isPendapatanSisi = in_array($akun->tipe, ['pendapatan', 'pendapatan_lain'], true);

        $saldo = 0.0;
        $baris = [];
        $totalDebit = 0.0;
        $totalKredit = 0.0;

        foreach ($rows as $row) {
            // REUSE persis resolusi kode akun dari LabaRugiFormalService —
            // TIDAK ditulis ulang di sini (Rule bisnis #47).
            $kode = $this->labaRugiService->resolveKodeAkun(
                $row->kategori_id,
                $row->kode_akun_coa,
                $row->kategori_tipe,
                $row->kategori_enum,
                $row->tipe_transaksi
            );

            if ($kode !== $kodeAkun) {
                continue;
            }

            $jumlah = (float) $row->jumlah;
            $debit = $isPendapatanSisi ? 0.0 : $jumlah;
            $kredit = $isPendapatanSisi ? $jumlah : 0.0;

            $saldo += $isDebetNormal ? ($debit - $kredit) : ($kredit - $debit);
            $totalDebit += $debit;
            $totalKredit += $kredit;

            $baris[] = [
                'id' => $row->id,
                'tanggal' => $row->tanggal_transaksi,
                'keterangan' => $row->keterangan,
                'debit' => $debit,
                'kredit' => $kredit,
                'saldo' => $saldo,
            ];
        }

        return [
            'akun' => $akun,
            'baris' => $baris,
            'total_debit' => $totalDebit,
            'total_kredit' => $totalKredit,
            'saldo_akhir' => $saldo,
        ];
    }
}
