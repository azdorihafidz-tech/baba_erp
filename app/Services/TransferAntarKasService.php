<?php

namespace App\Services;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\TransaksiKeuangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Transfer Antar Kas — mutasi dana dalam 1 cabang (mis. Tunai <-> Bank),
 * BEDA dari SetoranController (Transfer/Perpindahan Dana ANTAR cabang,
 * ada workflow approval + status_setoran/diterima_oleh_id). Transfer ini
 * INSTAN — kedua saldo Kas langsung terpengaruh dalam 1 transaksi, tidak
 * ada status menunggu/approval, karena uangnya tidak pernah keluar cabang
 * secara fisik.
 *
 * Pairing 2 baris TransaksiKeuangan pakai kolom referensi_type/referensi_id
 * (pola polymorphic generik existing, sama seperti asset_depreciation/
 * purchase_order) — BUKAN setoran_pair_id, karena kolom itu terikat erat ke
 * workflow approval Setoran (dipasangkan dengan status_setoran/
 * diterima_oleh_id) dan query SetoranController sendiri berasumsi
 * pasangannya kategori SETOR-IN/SETOR-OUT. Reuse akan membingungkan &
 * berisiko nyerempet logic itu. Zero migration baru — kolom referensi_type/
 * referensi_id sudah ada & nullable di transaksi_keuangans.
 *
 * Kategori MUTASI-IN/MUTASI-OUT sengaja 2 kategori satu-arah terpisah,
 * BUKAN 1 kategori tipe='keduanya' — LabaRugiFormalService::resolveKodeAkun()
 * override PAKSA transaksi kategori tipe='keduanya' sisi pemasukan ke kode
 * akun 4-1199 (Pendapatan Usaha Lainnya), walau kode_akun_coa kategori itu
 * sengaja NULL. Kalau dipakai 1 kategori dual-purpose, sisi masuk transfer
 * akan salah kehitung sebagai pendapatan di Laba Rugi Formal & Buku Besar —
 * melanggar prinsip "bukan pendapatan". 2 kategori satu-arah (persis pola
 * SETOR-IN/SETOR-OUT yang sudah terbukti aman) menghindari override ini
 * sepenuhnya.
 */
class TransferAntarKasService
{
    /**
     * @return array{trx_keluar: TransaksiKeuangan, trx_masuk: TransaksiKeuangan}
     */
    public function buatTransfer(
        int $kasAsalId,
        int $kasTujuanId,
        float $jumlah,
        string $tanggal,
        ?string $keterangan,
        int $userId
    ): array {
        if ($kasAsalId === $kasTujuanId) {
            throw ValidationException::withMessages([
                'kas_tujuan_id' => 'Kas asal dan kas tujuan tidak boleh sama.',
            ]);
        }

        if ($jumlah <= 0) {
            throw ValidationException::withMessages([
                'jumlah' => 'Jumlah transfer harus lebih besar dari 0.',
            ]);
        }

        return DB::transaction(function () use ($kasAsalId, $kasTujuanId, $jumlah, $tanggal, $keterangan, $userId) {
            $kasAsal   = Kas::lockForUpdate()->findOrFail($kasAsalId);
            $kasTujuan = Kas::lockForUpdate()->findOrFail($kasTujuanId);

            if ($kasAsal->cabang_id !== $kasTujuan->cabang_id) {
                throw ValidationException::withMessages([
                    'kas_tujuan_id' => 'Kas asal dan kas tujuan harus berada di cabang yang sama. '
                        . 'Untuk transfer antar cabang, gunakan menu Transfer/Perpindahan Dana.',
                ]);
            }

            if ((float) $kasAsal->saldo_sekarang < $jumlah) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Saldo kas "' . $kasAsal->nama_kas . '" tidak cukup. Saldo saat ini: Rp '
                        . number_format((float) $kasAsal->saldo_sekarang, 0, ',', '.'),
                ]);
            }

            $kategoriOut = KategoriTransaksi::where('kode', 'MUTASI-OUT')->first();
            $kategoriIn  = KategoriTransaksi::where('kode', 'MUTASI-IN')->first();
            if (!$kategoriOut || !$kategoriIn) {
                throw new \RuntimeException(
                    'Kategori MUTASI-IN/MUTASI-OUT belum ter-seed. Jalankan: php artisan db:seed --class=KategoriTransaksiSeeder'
                );
            }

            $prefix    = 'TRX-' . date('Ymd');
            $lastNomor = TransaksiKeuangan::withTrashed()
                ->where('nomor_transaksi', 'like', $prefix . '-%')
                ->orderByDesc('id')
                ->value('nomor_transaksi');
            $seq = 1;
            if ($lastNomor && preg_match('/(\d+)$/', $lastNomor, $m)) {
                $seq = ((int) $m[1]) + 1;
            }
            $nomorOut = $prefix . '-' . str_pad($seq++, 4, '0', STR_PAD_LEFT);
            $nomorIn  = $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $keteranganTrim = trim((string) $keterangan);
            $suffix         = $keteranganTrim !== '' ? ": {$keteranganTrim}" : '';

            $trxOut = TransaksiKeuangan::create([
                'cabang_id'         => $kasAsal->cabang_id,
                'kas_id'            => $kasAsal->id,
                'nomor_transaksi'   => $nomorOut,
                'tanggal_transaksi' => $tanggal,
                'tipe'              => TipeTransaksiKeuangan::Pengeluaran,
                'kategori'          => KategoriEnum::Lainnya,
                'kategori_id'       => $kategoriOut->id,
                'keterangan'        => "Transfer ke {$kasTujuan->nama_kas}{$suffix}",
                'jumlah'            => $jumlah,
                'referensi_type'    => 'transfer_antar_kas',
                'created_by'        => $userId,
            ]);

            $trxIn = TransaksiKeuangan::create([
                'cabang_id'         => $kasTujuan->cabang_id,
                'kas_id'            => $kasTujuan->id,
                'nomor_transaksi'   => $nomorIn,
                'tanggal_transaksi' => $tanggal,
                'tipe'              => TipeTransaksiKeuangan::Pemasukan,
                'kategori'          => KategoriEnum::Lainnya,
                'kategori_id'       => $kategoriIn->id,
                'keterangan'        => "Transfer dari {$kasAsal->nama_kas}{$suffix}",
                'jumlah'            => $jumlah,
                'referensi_type'    => 'transfer_antar_kas',
                'referensi_id'      => $trxOut->id,
                'created_by'        => $userId,
            ]);

            $trxOut->update(['referensi_id' => $trxIn->id]);

            $kasAsal->decrement('saldo_sekarang', $jumlah);
            $kasTujuan->increment('saldo_sekarang', $jumlah);

            activity('TransferAntarKas')->log(
                (auth()->user()?->name ?? 'System') . " transfer Rp " . number_format($jumlah, 0, ',', '.')
                . " dari {$kasAsal->nama_kas} ke {$kasTujuan->nama_kas} (cabang #{$kasAsal->cabang_id})"
            );

            return ['trx_keluar' => $trxOut->fresh(), 'trx_masuk' => $trxIn->fresh()];
        });
    }

    /**
     * Resolve pasangan transaksi dari salah satu sisi (keluar ATAU masuk).
     *
     * @return array{0: TransaksiKeuangan, 1: TransaksiKeuangan} [$trxKeluar, $trxMasuk]
     */
    public function resolvePasangan(TransaksiKeuangan $transaksi): array
    {
        if ($transaksi->referensi_type !== 'transfer_antar_kas') {
            throw new \RuntimeException('Transaksi ini bukan Transfer Antar Kas.');
        }

        $pasangan = TransaksiKeuangan::withTrashed()->find($transaksi->referensi_id);
        if (!$pasangan) {
            throw new \RuntimeException(
                'Pasangan transaksi tidak ditemukan (mungkin sudah dihapus atau data corrupted). '
                . 'Cek menu Data Terhapus sebelum menghapus transaksi ini.'
            );
        }

        return $transaksi->tipe === TipeTransaksiKeuangan::Pengeluaran
            ? [$transaksi, $pasangan]
            : [$pasangan, $transaksi];
    }

    public function hapusTransfer(TransaksiKeuangan $transaksi): void
    {
        [$trxOut, $trxIn] = $this->resolvePasangan($transaksi);

        DB::transaction(function () use ($trxOut, $trxIn) {
            // Balik saldo: kembalikan ke kas asal, tarik lagi dari kas tujuan
            if ($trxOut->kas_id) {
                Kas::where('id', $trxOut->kas_id)->increment('saldo_sekarang', (float) $trxOut->jumlah);
            }
            if ($trxIn->kas_id) {
                Kas::where('id', $trxIn->kas_id)->decrement('saldo_sekarang', (float) $trxOut->jumlah);
            }

            activity('TransferAntarKas')->log(
                (auth()->user()?->name ?? 'System')
                . " hapus transfer antar kas {$trxOut->nomor_transaksi} <-> {$trxIn->nomor_transaksi}, saldo dikembalikan"
            );

            $trxOut->delete();
            $trxIn->delete();
        });
    }
}
