<?php

namespace App\Services;

use App\Enums\KategoriTransaksi;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Cabang;
use App\Models\Kas;
use App\Models\Penggajian;
use App\Models\TransaksiKeuangan;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 5 — jurnal gaji (rancangan §8: jurnal otomatis dari gaji).
 * Slip gaji ditandai dibayar → TransaksiKeuangan pengeluaran kategori Gaji (COA 6-1101 Beban Gaji) dari kas
 * lokasi slip + saldo kas berkurang. Kas tidak dipilih → Kas Transfer lokasi itu (KasBawaanService).
 * Satu kali per slip (referensi penggajian). Gaji Rp 0 → hanya status.
 */
class JurnalGajiService
{
    public const REF = 'penggajian';

    /** BABA Tahap 7d: bagian gaji yang dipotong untuk ganti rugi barang hilang (tanpa kas). */
    public const REF_POTONGAN = 'penggajian_potongan';

    /** @return Kas|null kas yang dipakai (null bila total gaji 0) */
    public function bayar(Penggajian $slip, ?int $kasId, ?int $userId): ?Kas
    {
        return DB::transaction(function () use ($slip, $kasId, $userId) {
            $p = Penggajian::lockForUpdate()->findOrFail($slip->id);
            if (! in_array($p->status, ['draft', 'disetujui'], true)) {
                throw new \InvalidArgumentException('Slip gaji ini sudah dibayar atau tidak valid.');
            }
            $total = round((float) $p->total_gaji, 2);
            $kas = null;
            if ($total > 0 && ! TransaksiKeuangan::where('referensi_type', self::REF)->where('referensi_id', $p->id)->exists()) {
                $cabang = Cabang::findOrFail((int) $p->cabang_id);
                $kas = $kasId
                    ? Kas::where('id', $kasId)->where('cabang_id', $cabang->id)->where('is_active', true)->first()
                    : app(KasBawaanService::class)->pastikan($cabang)['transfer'];
                if (! $kas) {
                    throw new \InvalidArgumentException('Pilih kas milik ' . $cabang->nama_cabang . ' untuk membayar gaji ini.');
                }
                $kas->decrement('saldo_sekarang', $total);
                $nama = $p->karyawan?->nama_lengkap ?? 'karyawan';
                TransaksiKeuangan::create([
                    'cabang_id' => $cabang->id, 'kas_id' => $kas->id, 'nomor_transaksi' => 'TRX-GJ-' . $p->id, 'tanggal_transaksi' => today(),
                    'tipe' => TipeTransaksiKeuangan::Pengeluaran, 'kategori' => KategoriTransaksi::Gaji,
                    'keterangan' => mb_substr("Gaji {$nama} periode {$p->periode}", 0, 255), 'jumlah' => $total,
                    'referensi_type' => self::REF, 'referensi_id' => $p->id, 'created_by' => $userId,
                ]);
            }
            // BABA Tahap 7d: potongan ganti rugi barang hilang = bagian gaji yang tidak dibayar tunai/transfer.
            // Beban gaji bagian itu dicatat tanpa kas (bruto tetap tercatat), lalu dipulihkan sebagai Pemulihan Kerugian Stok.
            $gantiRugi = round((float) $p->potongan_ganti_rugi, 2);
            if ($gantiRugi > 0 && ! TransaksiKeuangan::where('referensi_type', self::REF_POTONGAN)->where('referensi_id', $p->id)->exists()) {
                TransaksiKeuangan::create([
                    'cabang_id' => $p->cabang_id, 'kas_id' => null, 'nomor_transaksi' => 'TRX-GJ-' . $p->id . '-GR', 'tanggal_transaksi' => today(),
                    'tipe' => TipeTransaksiKeuangan::Pengeluaran, 'kategori' => KategoriTransaksi::Gaji,
                    'keterangan' => mb_substr('Gaji ' . ($p->karyawan?->nama_lengkap ?? 'karyawan') . " periode {$p->periode} — dipotong ganti rugi barang hilang", 0, 255),
                    'jumlah' => $gantiRugi, 'referensi_type' => self::REF_POTONGAN, 'referensi_id' => $p->id, 'created_by' => $userId,
                ]);
                app(GantiRugiStokService::class)->slipDibayar($p, $userId);
            }
            $p->update(['status' => 'dibayar', 'tanggal_bayar' => today()]);

            return $kas;
        });
    }
}
