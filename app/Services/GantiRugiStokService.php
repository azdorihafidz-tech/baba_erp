<?php

namespace App\Services;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\TipeTransaksiKeuangan;
use App\Exceptions\StokOpnameException;
use App\Models\GantiRugiStok;
use App\Models\GantiRugiStokBayar;
use App\Models\Kas;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\StokOpnameRincian;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ERP BABA Tahap 7d — ganti rugi karyawan untuk barang HILANG (satu-satunya tempat aturannya).
 *  - Dibebankan setelah opname disetujui; nilai bawaan = harga pokok rincian, nilai lain wajib alasan.
 *  - Tunai → kas lokasi naik + PULIH-STOK (7-1103 Pemulihan Kerugian Stok).
 *  - Potong gaji (dicicil 1–12 bulan, mulai bulan berikutnya) → PenggajianService::generate memanggil potonganSlip()
 *    (baris "menunggu" per slip, maks. gaji yang tersedia); JurnalGajiService::bayar memanggil slipDibayar() →
 *    Beban Gaji dicatat bruto (gaji bersih + potongan), kas hanya berkurang gaji bersih, potongan = PULIH-STOK tanpa kas.
 */
class GantiRugiStokService
{
    public function __construct(private StokOpnameService $opname)
    {
    }

    public function boleh(User $u, \App\Models\StokOpname $o): bool
    {
        $o->loadMissing('cabang');

        return $u->can('stok_opname.ganti_rugi') && $o->status === 'selesai' && $o->cabang && $this->opname->bolehUbahLokasi($u, $o->cabang);
    }

    public function bolehKelola(User $u, GantiRugiStok $g): bool
    {
        $g->loadMissing('cabang');

        return $u->can('stok_opname.ganti_rugi') && $g->cabang && $this->opname->bolehUbahLokasi($u, $g->cabang);
    }

    /** Karyawan aktif yang bisa dibebani: karyawan lokasi opname dulu, lalu lokasi lain yang terlihat user. */
    public function karyawanUntuk(User $u, int $cabangId): Collection
    {
        $lokasi = $u->canAccessAllBranches() ? null : $u->cabangs()->pluck('cabangs.id')->push($cabangId)->all();

        return Karyawan::with('cabang')->where('status', 'aktif')->when($lokasi, fn ($q) => $q->whereIn('cabang_id', $lokasi))
            ->get()->sortBy(fn ($k) => ((int) $k->cabang_id === $cabangId ? '0' : '1') . $k->nama_lengkap)->values();
    }

    /** @param array{karyawan_id:mixed, nilai:mixed, alasan?:string, cara:string, cicilan_bulan?:mixed, catatan?:string} $d */
    public function bebankan(User $u, StokOpnameRincian $x, array $d): GantiRugiStok
    {
        $x->loadMissing('itemOpname.opname.cabang');
        $o = $x->itemOpname?->opname;
        if (! $o || ! $this->boleh($u, $o)) {
            throw new StokOpnameException('Anda tidak bisa membebankan ganti rugi untuk opname ini.');
        }
        if ($x->penyebab !== 'hilang') {
            throw new StokOpnameException('Ganti rugi karyawan hanya untuk barang yang hilang.');
        }
        $k = $this->karyawanUntuk($u, (int) $o->cabang_id)->firstWhere('id', (int) ($d['karyawan_id'] ?? 0));
        if (! $k) {
            throw new StokOpnameException('Pilih karyawan aktif yang dibebani.');
        }
        $cara = (string) ($d['cara'] ?? '');
        if (! array_key_exists($cara, GantiRugiStok::CARA)) {
            throw new StokOpnameException('Pilih cara bayar: tunai atau potong gaji.');
        }
        $nilai = round((float) ($d['nilai'] ?? 0), 2);
        if ($nilai <= 0) {
            throw new StokOpnameException('Isi nilai ganti rugi.');
        }
        $pokok = round((float) $x->nilai, 2);
        $sudah = (float) GantiRugiStok::where('stok_opname_rincian_id', $x->id)->where('status', '!=', 'batal')->sum('nilai_pokok');
        $bagianPokok = round(max(0, $pokok - $sudah), 2);
        $alasan = trim((string) ($d['alasan'] ?? ''));
        if ($bagianPokok <= 0 && mb_strlen($alasan) < 5) {
            throw new StokOpnameException('Kerugian barang ini sudah dibebankan seluruhnya. Tulis alasan bila memang ditambah.');
        }
        if (abs($nilai - $bagianPokok) > 0.5 && mb_strlen($alasan) < 5) {
            throw new StokOpnameException('Nilai berbeda dari harga pokok (' . $this->opname->rupiah($bagianPokok) . '): tulis alasannya, mis. "dibebankan harga jual sebagai sanksi".');
        }
        $cicilan = $cara === 'potong_gaji' ? max(1, min(12, (int) ($d['cicilan_bulan'] ?? 1))) : 1;

        return DB::transaction(function () use ($u, $x, $o, $k, $cara, $nilai, $bagianPokok, $alasan, $cicilan, $d) {
            $prefix = 'GR-' . now()->format('Ym') . '-';
            $akhir = GantiRugiStok::where('nomor', 'like', $prefix . '%')->lockForUpdate()->max(DB::raw('CAST(SUBSTRING(nomor, 11) AS UNSIGNED)'));

            return GantiRugiStok::create([
                'nomor' => $prefix . str_pad((string) (((int) $akhir) + 1), 4, '0', STR_PAD_LEFT), 'stok_opname_id' => $o->id, 'stok_opname_rincian_id' => $x->id,
                'cabang_id' => $o->cabang_id, 'karyawan_id' => $k->id, 'nilai' => $nilai, 'nilai_pokok' => min($bagianPokok, $nilai), 'alasan' => $alasan ?: null,
                'cara' => $cara, 'cicilan_bulan' => $cicilan, 'mulai_periode' => $cara === 'potong_gaji' ? now()->addMonthNoOverflow()->format('Y-m') : null,
                'status' => 'berjalan', 'catatan' => isset($d['catatan']) ? Str::limit(trim((string) $d['catatan']), 255, '') : null, 'dibuat_oleh' => $u->id,
            ]);
        });
    }

    /** Karyawan membayar tunai (cara apa pun — termasuk melunasi cicilan lebih cepat). */
    public function bayarTunai(User $u, GantiRugiStok $g, $jumlah, $kasId): GantiRugiStokBayar
    {
        if (! $this->bolehKelola($u, $g)) {
            throw new StokOpnameException('Anda tidak bisa menerima pembayaran ganti rugi ini.');
        }
        $jumlah = round((float) $jumlah, 2);
        $kas = Kas::whereKey((int) $kasId)->where('cabang_id', $g->cabang_id)->where('is_active', true)->first();
        if (! $kas) {
            throw new StokOpnameException('Pilih kas lokasi ini yang menerima uangnya.');
        }

        return DB::transaction(function () use ($u, $g, $jumlah, $kas) {
            $g = GantiRugiStok::with('karyawan')->whereKey($g->id)->lockForUpdate()->firstOrFail();
            $menunggu = (float) $g->bayar()->where('status', 'menunggu')->sum('jumlah');
            $bisa = round($g->nilai - $g->terbayar - $menunggu, 2);
            if ($g->status !== 'berjalan' || $jumlah <= 0 || $jumlah > $bisa + 0.005) {
                throw new StokOpnameException($g->status !== 'berjalan' ? 'Ganti rugi ini sudah ' . strtolower($g->status_label) . '.'
                    : 'Jumlah harus antara Rp 1 dan ' . $this->opname->rupiah($bisa) . ($menunggu > 0 ? ' (sebagian sudah masuk slip gaji yang belum dibayar)' : '') . '.');
            }
            $trx = $this->jurnal($g, $jumlah, $kas, 'Ganti rugi tunai ' . ($g->karyawan?->nama_lengkap ?? 'karyawan') . " ({$g->nomor})", $u->id, 'ganti_rugi_stok', $g->id);
            $kas->increment('saldo_sekarang', $jumlah);
            $b = $g->bayar()->create(['jenis' => 'tunai', 'jumlah' => $jumlah, 'kas_id' => $kas->id, 'status' => 'dibayar', 'transaksi_keuangan_id' => $trx->id, 'user_id' => $u->id]);
            $this->tambahTerbayar($g, $jumlah);

            return $b;
        });
    }

    public function batal(User $u, GantiRugiStok $g, string $alasan): GantiRugiStok
    {
        if (! $this->bolehKelola($u, $g)) {
            throw new StokOpnameException('Anda tidak bisa membatalkan ganti rugi ini.');
        }
        if (mb_strlen(trim($alasan)) < 5) {
            throw new StokOpnameException('Tulis alasan pembatalan (minimal 5 huruf).');
        }

        return DB::transaction(function () use ($u, $g, $alasan) {
            $g = GantiRugiStok::whereKey($g->id)->lockForUpdate()->firstOrFail();
            if ($g->status !== 'berjalan' || $g->terbayar > 0) {
                throw new StokOpnameException('Ganti rugi yang sudah dibayar (sebagian) atau sudah selesai tidak bisa dibatalkan.');
            }
            // potongan yang sudah masuk slip gaji belum dibayar dikeluarkan dari slip itu
            foreach ($g->bayar()->where('status', 'menunggu')->get() as $b) {
                $p = Penggajian::whereKey($b->penggajian_id)->lockForUpdate()->first();
                if ($p && $p->status !== 'dibayar') {
                    $p->update(['potongan_ganti_rugi' => max(0, round((float) $p->potongan_ganti_rugi - $b->jumlah, 2)),
                        'total_gaji' => round((float) $p->total_gaji + $b->jumlah, 2)]);
                }
                $b->delete();
            }
            $g->update(['status' => 'batal', 'dibatalkan_oleh' => $u->id, 'alasan_batal' => Str::limit(trim($alasan), 500, '')]);

            return $g;
        });
    }

    // ───────────── potong gaji (dipanggil PenggajianService & JurnalGajiService) ─────────────

    /**
     * Isi potongan ganti rugi slip gaji: hapus baris "menunggu" lama slip ini, hitung cicilan yang jatuh tempo (maks. $tersedia),
     * buat baris "menunggu" baru. Pemanggil di dalam DB::transaction. @return float total potongan
     */
    public function potonganSlip(Penggajian $p, float $tersedia): float
    {
        if ($p->status === 'dibayar') {
            return (float) $p->potongan_ganti_rugi;
        }
        GantiRugiStokBayar::where('penggajian_id', $p->id)->where('status', 'menunggu')->delete();
        $total = 0.0;
        $daftar = GantiRugiStok::where('karyawan_id', $p->karyawan_id)->where('cara', 'potong_gaji')->where('status', 'berjalan')
            ->where('mulai_periode', '<=', $p->periode)->orderBy('id')->lockForUpdate()->get();
        foreach ($daftar as $g) {
            if ($g->bayar()->where('jenis', 'potong_gaji')->where('periode', $p->periode)->where('status', 'dibayar')->exists()) {
                continue; // satu cicilan per periode
            }
            $menungguLain = (float) $g->bayar()->where('status', 'menunggu')->sum('jumlah');
            $sisa = round($g->nilai - $g->terbayar - $menungguLain, 2);
            $jumlah = round(min($g->cicilan, $sisa, max(0, $tersedia - $total)), 2);
            if ($jumlah <= 0) {
                continue;
            }
            $g->bayar()->create(['jenis' => 'potong_gaji', 'jumlah' => $jumlah, 'periode' => $p->periode, 'penggajian_id' => $p->id, 'status' => 'menunggu']);
            $total += $jumlah;
        }

        return round($total, 2);
    }

    /** Slip gaji ditandai dibayar: potongan "menunggu" jadi terbayar + PULIH-STOK tanpa kas. Pemanggil di dalam transaksi. */
    public function slipDibayar(Penggajian $p, ?int $userId): float
    {
        $baris = GantiRugiStokBayar::with('gantiRugi')->where('penggajian_id', $p->id)->where('status', 'menunggu')->lockForUpdate()->get();
        $total = round((float) $baris->sum('jumlah'), 2);
        if ($total <= 0) {
            return 0.0;
        }
        $g0 = $baris->first()->gantiRugi;
        $trx = $this->jurnal($g0, $total, null, 'Ganti rugi potong gaji ' . ($p->karyawan?->nama_lengkap ?? 'karyawan') . " periode {$p->periode}", $userId, 'penggajian_ganti_rugi', $p->id, (int) $p->cabang_id);
        foreach ($baris as $b) {
            $b->update(['status' => 'dibayar', 'transaksi_keuangan_id' => $trx->id, 'user_id' => $userId]);
            $this->tambahTerbayar(GantiRugiStok::whereKey($b->ganti_rugi_stok_id)->lockForUpdate()->first(), $b->jumlah);
        }

        return $total;
    }

    private function tambahTerbayar(GantiRugiStok $g, float $jumlah): void
    {
        $terbayar = round($g->terbayar + $jumlah, 2);
        $g->update(['terbayar' => $terbayar, 'status' => $terbayar + 0.005 >= $g->nilai ? 'lunas' : 'berjalan']);
    }

    private function jurnal(GantiRugiStok $g, float $jumlah, ?Kas $kas, string $ket, ?int $userId, string $refType, int $refId, ?int $cabangId = null): TransaksiKeuangan
    {
        $prefix = 'GRS-' . now()->format('Ymd') . '-';
        $akhir = TransaksiKeuangan::withTrashed()->where('nomor_transaksi', 'like', $prefix . '%')->lockForUpdate()
            ->max(DB::raw("CAST(SUBSTRING_INDEX(nomor_transaksi, '-', -1) AS UNSIGNED)"));

        return TransaksiKeuangan::create([
            'cabang_id' => $cabangId ?? $g->cabang_id, 'kas_id' => $kas?->id, 'nomor_transaksi' => $prefix . str_pad((string) (((int) $akhir) + 1), 4, '0', STR_PAD_LEFT),
            'tanggal_transaksi' => today()->toDateString(), 'tipe' => TipeTransaksiKeuangan::Pemasukan, 'kategori' => KategoriEnum::Lainnya,
            'kategori_id' => StokRusakService::kategoriPulih()->id, 'keterangan' => Str::limit($ket, 250, ''), 'jumlah' => $jumlah,
            'referensi_type' => $refType, 'referensi_id' => $refId, 'created_by' => $userId,
        ]);
    }
}
