<?php

namespace App\Services;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\StatusOrder;
use App\Enums\StatusSetoranKasir;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Cabang;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\OrderPayment;
use App\Models\Setoran;
use App\Models\SetoranApproval;
use App\Models\SetoranDetail;
use App\Models\TransaksiKeuangan;
use App\Support\ClosingLock;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 5 D'mentai — Setoran Kasir (rekonsiliasi kas harian cabang -> HO).
 *
 * SCOPE SENGAJA DIBATASI ke KAS TUNAI saja: metode non-tunai (transfer/qris)
 * sudah otomatis settle ke kas non-tunai cabang saat order dibuat
 * (lihat PenjualanService — order langsung pilih Kas sesuai
 * TipePembayaran::kasKategori()), jadi TIDAK ada uang fisik yang perlu
 * "diserahkan" kasir untuk metode itu. Breakdown per metode di
 * `setoran_details` murni INFORMASI (transparansi ke HO), bukan yang
 * dipindahkan saat approve — cuma kas tunai yang benar-benar berpindah.
 */
class SetoranKasirService
{
    /** Hitung otomatis breakdown per metode dari order_payments, murni READ. */
    public function hitungOtomatis(int $cabangId, string $tanggal): array
    {
        $rows = OrderPayment::query()
            ->whereHas('order', function ($q) use ($cabangId, $tanggal) {
                $q->where('cabang_id', $cabangId)
                    ->whereDate('tanggal_order', $tanggal)
                    ->penjualanSah();
            })
            ->selectRaw('metode, SUM(jumlah) as total')
            ->groupBy('metode')
            ->pluck('total', 'metode');

        $perMetode = [];
        $total = 0.0;
        foreach (['tunai', 'transfer', 'qris'] as $metode) {
            $jumlah = (float) ($rows[$metode] ?? 0);
            $perMetode[$metode] = $jumlah;
            $total += $jumlah;
        }

        return [
            'total' => $total,
            'per_metode' => $perMetode,
            'tunai' => $perMetode['tunai'],
        ];
    }

    /**
     * Submit setoran baru, atau REVISE setoran yang sebelumnya ditolak
     * (update in place, bukan bikin baris baru — unique(cabang_id,tanggal)).
     */
    public function submitSetoran(array $data): Setoran
    {
        $cabangId = (int) $data['cabang_id'];
        $tanggal = $data['tanggal'];

        $existing = Setoran::where('cabang_id', $cabangId)->where('tanggal', $tanggal)->first();
        if ($existing && $existing->status !== StatusSetoranKasir::Rejected) {
            throw new \Exception("Setoran untuk cabang ini tanggal {$tanggal} sudah pernah disubmit (status: {$existing->status->label()}).");
        }

        $hitung = $this->hitungOtomatis($cabangId, $tanggal);
        $totalDisetor = (float) $data['total_disetor'];
        $selisih = $totalDisetor - $hitung['tunai'];

        return DB::transaction(function () use ($data, $cabangId, $tanggal, $hitung, $totalDisetor, $selisih, $existing) {
            $payload = [
                'cabang_id' => $cabangId,
                'tanggal' => $tanggal,
                'disubmit_oleh' => $data['user_id'],
                'disubmit_pada' => now(),
                'total_penjualan_sistem' => $hitung['total'],
                'total_disetor' => $totalDisetor,
                'selisih' => $selisih,
                'status' => StatusSetoranKasir::Menunggu,
                'disetujui_oleh' => null,
                'disetujui_pada' => null,
                'ditolak_alasan' => null,
                'bukti_foto' => $data['bukti_foto'] ?? null,
                'catatan_kasir' => $data['catatan_kasir'] ?? null,
            ];

            if ($existing) {
                $existing->update($payload);
                $setoran = $existing;
                $setoran->details()->delete();
            } else {
                $setoran = Setoran::create($payload);
            }

            foreach ($hitung['per_metode'] as $metode => $jumlah) {
                SetoranDetail::create([
                    'setoran_id' => $setoran->id,
                    'metode' => $metode,
                    'jumlah_sistem' => $jumlah,
                    'jumlah_fisik' => $metode === 'tunai' ? $totalDisetor : $jumlah,
                ]);
            }

            SetoranApproval::create([
                'setoran_id' => $setoran->id,
                'action' => 'submit',
                'user_id' => $data['user_id'],
                'catatan' => $data['catatan_kasir'] ?? null,
                'dilakukan_pada' => now(),
            ]);

            return $setoran;
        });
    }

    /**
     * Approve: pindahkan KAS TUNAI dari cabang ke HO via 2 TransaksiKeuangan
     * (bukan pair via `setoran_pair_id` seperti Transfer Dana existing — pakai
     * `referensi_type='setoran_kasir'`+`referensi_id` supaya tidak nyerempet
     * state machine SetoranController). Uang baru berpindah DI SINI (bukan
     * saat submit) — sebelum approve, `setorans` murni laporan, kas tidak
     * disentuh sama sekali.
     */
    public function approveSetoran(Setoran $setoran, int $userId, ?string $catatanHo = null): Setoran
    {
        if ($setoran->status !== StatusSetoranKasir::Menunggu) {
            throw new \Exception('Setoran sudah diproses sebelumnya (status: ' . $setoran->status->label() . ').');
        }

        $kasCabang = Kas::aktif()->where('cabang_id', $setoran->cabang_id)->where('default_untuk', 'tunai')->first()
            ?? Kas::aktif()->where('cabang_id', $setoran->cabang_id)->first();
        if (! $kasCabang) {
            throw new \Exception('Cabang ini belum punya Kas aktif — tidak bisa memproses setoran.');
        }

        $ho = Cabang::gudangPusat()->first();
        if (! $ho) {
            throw new \Exception('Cabang Gudang Pusat (HO) belum di-setup di sistem.');
        }
        $kasHo = Kas::aktif()->where('cabang_id', $ho->id)->where('default_untuk', 'tunai')->first()
            ?? Kas::aktif()->where('cabang_id', $ho->id)->first();
        if (! $kasHo) {
            throw new \Exception('Gudang Pusat (HO) belum punya Kas aktif — tidak bisa memproses setoran.');
        }

        $tunaiSetor = (float) $setoran->details()->where('metode', 'tunai')->value('jumlah_fisik');
        $kategoriOut = KategoriTransaksi::where('kode', 'SETORKSR-OUT')->first();
        $kategoriIn = KategoriTransaksi::where('kode', 'SETORKSR-IN')->first();
        $nomorPrefix = 'SETKSR-' . $setoran->tanggal->format('Ymd') . '-' . $setoran->cabang_id;

        // Closing / Tutup Buku: kalau tanggal setoran sudah di-closing (di cabang
        // atau di pusat), perpindahan uang dicatat pada tanggal persetujuan —
        // uang memang baru berpindah saat HO menyetujui. Angka closing yang
        // sudah dibekukan tidak berubah.
        $tanggalTrx = (ClosingLock::terkunci((int) $setoran->cabang_id, $setoran->tanggal)
            || ClosingLock::terkunci((int) $kasHo->cabang_id, $setoran->tanggal))
            ? now()->startOfDay()
            : $setoran->tanggal;

        return DB::transaction(function () use ($setoran, $userId, $catatanHo, $kasCabang, $kasHo, $tunaiSetor, $kategoriOut, $kategoriIn, $nomorPrefix, $tanggalTrx) {
            $trxOut = TransaksiKeuangan::create([
                'cabang_id' => $setoran->cabang_id,
                'kas_id' => $kasCabang->id,
                'nomor_transaksi' => $nomorPrefix . '-OUT',
                'tanggal_transaksi' => $tanggalTrx,
                'tipe' => TipeTransaksiKeuangan::Pengeluaran,
                'kategori' => KategoriEnum::Lainnya,
                'kategori_id' => $kategoriOut?->id,
                'keterangan' => 'Setoran Kasir ke HO — ' . $setoran->tanggal->format('d/m/Y'),
                'jumlah' => $tunaiSetor,
                'referensi_type' => 'setoran_kasir',
                'referensi_id' => $setoran->id,
                'created_by' => $userId,
            ]);

            $trxIn = TransaksiKeuangan::create([
                'cabang_id' => $kasHo->cabang_id,
                'kas_id' => $kasHo->id,
                'nomor_transaksi' => $nomorPrefix . '-IN',
                'tanggal_transaksi' => $tanggalTrx,
                'tipe' => TipeTransaksiKeuangan::Pemasukan,
                'kategori' => KategoriEnum::Lainnya,
                'kategori_id' => $kategoriIn?->id,
                'keterangan' => 'Terima Setoran Kasir dari ' . $setoran->cabang->nama_cabang . ' — ' . $setoran->tanggal->format('d/m/Y'),
                'jumlah' => $tunaiSetor,
                'referensi_type' => 'setoran_kasir',
                'referensi_id' => $setoran->id,
                'created_by' => $userId,
            ]);

            $kasCabang->decrement('saldo_sekarang', $tunaiSetor);
            $kasHo->increment('saldo_sekarang', $tunaiSetor);

            $setoran->update([
                'status' => StatusSetoranKasir::Approved,
                'disetujui_oleh' => $userId,
                'disetujui_pada' => now(),
                'catatan_ho' => $catatanHo,
                'transaksi_out_id' => $trxOut->id,
                'transaksi_in_id' => $trxIn->id,
            ]);

            SetoranApproval::create([
                'setoran_id' => $setoran->id,
                'action' => 'approve',
                'user_id' => $userId,
                'catatan' => $catatanHo,
                'dilakukan_pada' => now(),
            ]);

            return $setoran;
        });
    }

    public function rejectSetoran(Setoran $setoran, int $userId, string $alasan): Setoran
    {
        if ($setoran->status !== StatusSetoranKasir::Menunggu) {
            throw new \Exception('Setoran sudah diproses sebelumnya (status: ' . $setoran->status->label() . ').');
        }

        return DB::transaction(function () use ($setoran, $userId, $alasan) {
            $setoran->update([
                'status' => StatusSetoranKasir::Rejected,
                'ditolak_alasan' => $alasan,
            ]);

            SetoranApproval::create([
                'setoran_id' => $setoran->id,
                'action' => 'reject',
                'user_id' => $userId,
                'catatan' => $alasan,
                'dilakukan_pada' => now(),
            ]);

            return $setoran;
        });
    }
}
