<?php

namespace App\Services;

use App\Enums\KategoriTransaksi;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Cabang;
use App\Models\Kas;
use App\Models\PesananOnline;
use App\Models\PesananOnlineBayar;
use App\Models\ReturOnline;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 4 — keuangan Pesanan Online GUDANG (satu-satunya tempat).
 *
 * Sama dengan kasir (PenjualanService): pendapatan dicatat saat UANG DITERIMA, sebagai TransaksiKeuangan
 * pemasukan kategori Penjualan (→ COA 4-1102) + saldo Kas bertambah. Kas penerima = kas bawaan gudang
 * (KasBawaanService: transfer → Kas Transfer, tunai → Kas Tunai). Dana yang dikembalikan (pesanan lunas
 * dibatalkan, retur lunas disetujui) = pemasukan Penjualan NEGATIF + saldo Kas berkurang (pola ReturInternalService).
 * Tagihan belum dibayar = Piutang (lihat piutang()) — belum menjadi pendapatan.
 * HPP: hpp() = HPP barang keluar − HPP barang retur yang kembali ke gudang (dipakai LabaRugiFormalService).
 * Pesanan ke upline = urusan antar member, tidak masuk keuangan BABA.
 * Idempotent per sumber (referensi_type + referensi_id): dipanggil ulang tidak mencatat dua kali.
 */
class JurnalOnlineService
{
    public const REF_BAYAR = 'pesanan_online_bayar';
    public const REF_BATAL = 'pesanan_online_batal';
    public const REF_RETUR = 'retur_online';

    public function kas(PesananOnline|ReturOnline $sumber, string $metode): Kas
    {
        $gudang = Cabang::findOrFail((int) $sumber->sumber_cabang_id);

        return app(KasBawaanService::class)->pastikan($gudang)[$metode === 'tunai' ? 'tunai' : 'transfer'];
    }

    /** Bukti bayar diterima (pesanan gudang). Nominal = sisa yang harus dibayar saat itu (total − retur). */
    public function bayarDiterima(PesananOnline $p, PesananOnlineBayar $b, ?int $userId): void
    {
        if ($p->sumber !== 'gudang') {
            return;
        }
        $nilai = round((float) $p->total - (float) $p->nilai_retur, 2);
        $this->catat($p, self::REF_BAYAR, $b->id, 'TRX-' . $p->nomor . '-B' . $b->id, 'transfer', $nilai,
            "Pesanan online {$p->nomor} — {$this->pembeli($p)}" . ($p->cara_bayar === 'tagihan' ? ' (pelunasan tagihan)' : ''), $userId);
    }

    /** Pesanan gudang yang SUDAH LUNAS dibatalkan admin → dana wajib dikembalikan. */
    public function batalLunas(PesananOnline $p, ?int $userId): void
    {
        if ($p->sumber !== 'gudang' || $p->status_bayar !== 'lunas') {
            return;
        }
        $masuk = (float) TransaksiKeuangan::where('referensi_type', self::REF_BAYAR)
            ->whereIn('referensi_id', $p->bayar()->pluck('id'))->sum('jumlah');
        if ($masuk <= 0) {
            return;
        }
        $this->catat($p, self::REF_BATAL, $p->id, 'TRX-' . $p->nomor . '-BTL', 'transfer', -$masuk,
            "Pesanan online {$p->nomor} dibatalkan — dana dikembalikan ke {$this->pembeli($p)}", $userId);
    }

    /** Retur gudang disetujui dengan dana dikembalikan (transfer / tunai). Potong tagihan = tanpa uang. */
    public function returDisetujui(ReturOnline $r, ?int $userId): void
    {
        if ($r->sumber !== 'gudang' || ! in_array($r->pengembalian, ['transfer', 'tunai'], true) || (float) $r->nilai <= 0) {
            return;
        }
        $p = $r->pesanan;
        $this->catat($r, self::REF_RETUR, $r->id, 'TRX-' . $r->nomor, $r->pengembalian, -(float) $r->nilai,
            "Retur online {$r->nomor} (pesanan {$p->nomor}) — dana dikembalikan ke {$this->pembeli($p)}", $userId);
    }

    private function catat(PesananOnline|ReturOnline $sumber, string $refType, int $refId, string $nomor, string $metode, float $nilai, string $ket, ?int $userId): void
    {
        if ($nilai == 0.0 || TransaksiKeuangan::where('referensi_type', $refType)->where('referensi_id', $refId)->exists()) {
            return;
        }
        $kas = $this->kas($sumber, $metode);
        $nilai > 0 ? $kas->increment('saldo_sekarang', $nilai) : $kas->decrement('saldo_sekarang', -$nilai);
        TransaksiKeuangan::create([
            'cabang_id' => $kas->cabang_id, 'kas_id' => $kas->id, 'nomor_transaksi' => mb_substr($nomor, 0, 50), 'tanggal_transaksi' => today(),
            'tipe' => TipeTransaksiKeuangan::Pemasukan, 'kategori' => KategoriTransaksi::Penjualan,
            'keterangan' => mb_substr($ket, 0, 255), 'jumlah' => $nilai, 'referensi_type' => $refType, 'referensi_id' => $refId, 'created_by' => $userId,
        ]);
    }

    private function pembeli(PesananOnline $p): string
    {
        $m = $p->member;

        return $m ? trim($m->nama . ($m->no_id ? " ({$m->no_id})" : '')) : 'member';
    }

    /**
     * HPP Pesanan Online gudang dalam periode: barang keluar (tanggal dikirim / siap diambil) − barang retur
     * yang kembali ke gudang (tanggal retur disetujui, dengan HPP asal). Pasangan persediaan yang berkurang/bertambah.
     */
    public function hpp(Carbon $mulai, Carbon $akhir, ?int $cabangId = null): float
    {
        $dari = $mulai->copy()->startOfDay();
        $sampai = $akhir->copy()->endOfDay();
        $keluar = DB::table('pesanan_online_item as i')->join('pesanan_online as p', 'p.id', '=', 'i.pesanan_online_id')
            ->where('p.sumber', 'gudang')->where('p.stok_keluar', true)->whereBetween('p.dikirim_at', [$dari, $sampai])
            ->when($cabangId, fn ($q) => $q->where('p.sumber_cabang_id', $cabangId))
            ->sum(DB::raw('i.qty * COALESCE(i.hpp, 0)'));
        $kembali = DB::table('retur_online_item as ri')->join('retur_online as r', 'r.id', '=', 'ri.retur_online_id')
            ->join('pesanan_online_item as i', 'i.id', '=', 'ri.pesanan_online_item_id')
            ->where('r.sumber', 'gudang')->where('r.status', 'disetujui')->whereBetween('r.diputus_at', [$dari, $sampai])
            ->when($cabangId, fn ($q) => $q->where('r.sumber_cabang_id', $cabangId))
            ->sum(DB::raw('ri.qty * COALESCE(i.hpp, 0)'));

        return round((float) $keluar - (float) $kembali, 2);
    }

    /** Piutang member per tanggal: tagihan pesanan gudang yang barangnya sudah keluar tetapi belum lunas. */
    public function piutang(?int $cabangId = null): float
    {
        return round((float) PesananOnline::where('sumber', 'gudang')->where('cara_bayar', 'tagihan')->where('stok_keluar', true)
            ->where('status_bayar', '!=', 'lunas')->where('status', '!=', 'batal')
            ->when($cabangId, fn ($q) => $q->where('sumber_cabang_id', $cabangId))
            ->sum(DB::raw('total - nilai_retur')), 2);
    }
}
