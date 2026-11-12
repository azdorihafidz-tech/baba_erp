<?php

namespace App\Services;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\TipeTransaksiKeuangan;
use App\Exceptions\StokOpnameException;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\StokOpname;
use App\Models\StokRusak;
use App\Models\StokRusakTindakan;
use App\Models\Supplier;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ERP BABA Tahap 7c — stok rusak ditahan (satu-satunya tempat aturannya). Kerugian sudah dicatat saat opname disetujui
 * (SO-RUSAK); tindak lanjut hanya MEMULIHKAN bila ada yang kembali:
 *  - pakai_lagi / pemasok_ganti: barang kembali ke stok jual (batch seharga pokok asal) + kerugian dibatalkan (SO-RUSAK negatif, tanpa kas);
 *  - pemasok_uang: uang masuk ke kas lokasi → Pemulihan Kerugian Stok (PULIH-STOK 7-1103, saldo kas naik);
 *  - pemasok_potong: tanpa jurnal (penghematan terlihat saat membayar pemasok lebih kecil);
 *  - musnahkan: tanpa jurnal, foto wajib.
 */
class StokRusakService
{
    public const KATEGORI_PULIH = ['kode' => 'PULIH-STOK', 'nama' => 'Pemulihan Kerugian Stok', 'coa' => '7-1103'];

    public function __construct(private StokService $stok, private StokOpnameService $opname)
    {
    }

    public function boleh(User $u, StokRusak $r): bool
    {
        $r->loadMissing('cabang');

        return $u->can('stok_opname.stok_rusak') && $r->cabang && $this->opname->bolehUbahLokasi($u, $r->cabang);
    }

    /** Dipanggil StokOpnameService::setujui (di dalam transaksinya): setiap rincian "rusak" → stok rusak ditahan. */
    public function dariOpname(StokOpname $o): int
    {
        $n = 0;
        $rincian = \App\Models\StokOpnameRincian::with('itemOpname')->whereHas('itemOpname', fn ($q) => $q->where('stok_opname_id', $o->id))
            ->where('penyebab', 'rusak')->get();
        foreach ($rincian as $x) {
            StokRusak::firstOrCreate(['stok_opname_rincian_id' => $x->id], [
                'cabang_id' => $o->cabang_id, 'item_id' => $x->itemOpname->item_id, 'stok_opname_id' => $o->id, 'satuan' => $x->itemOpname->satuan,
                'qty' => $x->qty, 'qty_sisa' => $x->qty, 'harga_pokok' => $x->qty > 0 ? round((float) $x->nilai / $x->qty, 4) : 0,
                'status' => 'ditahan', 'catatan' => $x->catatan,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * @param array{jenis:string, qty:mixed, supplier_id?:mixed, kas_id?:mixed, nominal?:mixed, catatan?:string, foto?:string} $d  foto = isi file biner
     */
    public function tindak(User $u, StokRusak $r, array $d): StokRusakTindakan
    {
        if (! $this->boleh($u, $r)) {
            throw new StokOpnameException(StokOpnameService::lokasiMitra($r->cabang) && $u->canAccessAllBranches()
                ? 'Stok rusak toko mitra ditindaklanjuti pemilik toko itu sendiri.' : 'Anda tidak punya izin mengelola stok rusak di lokasi ini.');
        }
        $jenis = (string) ($d['jenis'] ?? '');
        if (! array_key_exists($jenis, StokRusakTindakan::JENIS)) {
            throw new StokOpnameException('Pilih tindak lanjut.');
        }
        $qty = round((float) ($d['qty'] ?? 0), 3);
        if ($qty <= 0) {
            throw new StokOpnameException('Isi jumlah barang yang ditindaklanjuti.');
        }
        if (! StokOpnameService::bolehDesimal($r->satuan) && abs($qty - round($qty)) > 0.0001) {
            throw new StokOpnameException("Jumlah dihitung per {$r->satuan}: isi bilangan bulat.");
        }
        $catatan = isset($d['catatan']) ? Str::limit(trim((string) $d['catatan']), 500, '') : null;
        $supplierId = ! empty($d['supplier_id']) ? (int) $d['supplier_id'] : null;
        if ($supplierId && ! Supplier::whereKey($supplierId)->exists()) {
            throw new StokOpnameException('Pemasok tidak ditemukan.');
        }
        $kas = null;
        $nominal = 0.0;
        if ($jenis === 'pemasok_uang') {
            $nominal = round((float) ($d['nominal'] ?? 0), 2);
            if ($nominal <= 0) {
                throw new StokOpnameException('Isi jumlah uang yang dikembalikan pemasok.');
            }
            $kas = Kas::whereKey((int) ($d['kas_id'] ?? 0))->where('cabang_id', $r->cabang_id)->where('is_active', true)->first();
            if (! $kas) {
                throw new StokOpnameException('Pilih kas lokasi ini yang menerima uangnya.');
            }
        }
        if ($jenis === 'musnahkan' && empty($d['foto'])) {
            throw new StokOpnameException('Barang yang dimusnahkan wajib difoto sebagai bukti.');
        }
        if (in_array($jenis, ['pemasok_potong'], true) && ! $catatan) {
            throw new StokOpnameException('Tulis catatan tagihan pemasok yang dipotong (mis. nomor PO / faktur).');
        }
        $foto = ! empty($d['foto']) ? $this->simpanFoto($r, (string) $d['foto']) : null;

        try {
            return DB::transaction(function () use ($u, $r, $jenis, $qty, $catatan, $supplierId, $kas, $nominal, $foto) {
                $r = StokRusak::with(['barang', 'cabang'])->whereKey($r->id)->lockForUpdate()->firstOrFail();
                if ($r->status !== 'ditahan' || $qty > $r->qty_sisa + 0.0005) {
                    throw new StokOpnameException('Jumlah melebihi sisa stok rusak (' . $this->opname->angka($r->qty_sisa) . " {$r->satuan}).");
                }
                $nilaiPokok = round($qty * $r->harga_pokok, 2);
                $pulih = 0.0;
                $trx = null;
                $nama = $r->barang?->nama_item ?? 'barang';
                if (in_array($jenis, ['pakai_lagi', 'pemasok_ganti'], true)) {
                    // barang kembali ke stok jual seharga pokok asal, kerugian rusak dibatalkan sebesar nilai pokoknya
                    $this->stok->masuk((int) $r->item_id, (int) $r->cabang_id, $qty, ($jenis === 'pakai_lagi' ? 'Stok rusak dipakai lagi' : 'Pengganti barang rusak dari pemasok') . " #{$r->id}",
                        'stok_rusak', $r->id, $r->harga_pokok);
                    $pulih = $nilaiPokok;
                    if ($pulih > 0) {
                        $trx = $this->jurnal($r, StokOpnameService::kategori('rusak'), TipeTransaksiKeuangan::Pengeluaran, -$pulih, null,
                            "Stok rusak {$nama}: " . StokRusakTindakan::JENIS[$jenis] . ' (kerugian dibatalkan)', $u);
                    }
                } elseif ($jenis === 'pemasok_uang') {
                    $pulih = $nominal;
                    $trx = $this->jurnal($r, self::kategoriPulih(), TipeTransaksiKeuangan::Pemasukan, $nominal, $kas,
                        "Stok rusak {$nama}: uang pengganti dari pemasok", $u);
                    $kas->increment('saldo_sekarang', $nominal);
                }
                $t = StokRusakTindakan::create(['stok_rusak_id' => $r->id, 'jenis' => $jenis, 'qty' => $qty, 'nilai_pokok' => $nilaiPokok, 'nilai_pulih' => $pulih,
                    'supplier_id' => $supplierId, 'kas_id' => $kas?->id, 'transaksi_keuangan_id' => $trx?->id, 'foto' => $foto, 'catatan' => $catatan,
                    'user_id' => $u->id, 'created_at' => now()]);
                $sisa = round($r->qty_sisa - $qty, 3);
                $r->update(['qty_sisa' => max(0, $sisa), 'status' => $sisa <= 0.0005 ? 'selesai' : 'ditahan']);

                return $t;
            });
        } catch (\Throwable $e) {
            if ($foto) {
                Storage::disk('local')->delete($foto);
            }
            throw $e;
        }
    }

    private function jurnal(StokRusak $r, KategoriTransaksi $kat, TipeTransaksiKeuangan $tipe, float $jumlah, ?Kas $kas, string $ket, User $u): TransaksiKeuangan
    {
        $prefix = 'RSK-' . now()->format('Ymd') . '-';
        $akhir = TransaksiKeuangan::withTrashed()->where('nomor_transaksi', 'like', $prefix . '%')->lockForUpdate()
            ->max(DB::raw("CAST(SUBSTRING_INDEX(nomor_transaksi, '-', -1) AS UNSIGNED)"));

        return TransaksiKeuangan::create([
            'cabang_id' => $r->cabang_id, 'kas_id' => $kas?->id, 'nomor_transaksi' => $prefix . str_pad((string) (((int) $akhir) + 1), 4, '0', STR_PAD_LEFT),
            'tanggal_transaksi' => today()->toDateString(), 'tipe' => $tipe, 'kategori' => KategoriEnum::Lainnya, 'kategori_id' => $kat->id,
            'keterangan' => Str::limit($ket, 250, ''), 'jumlah' => $jumlah, 'referensi_type' => 'stok_rusak', 'referensi_id' => $r->id, 'created_by' => $u->id,
        ]);
    }

    public static function kategoriPulih(): KategoriTransaksi
    {
        return KategoriTransaksi::firstOrCreate(['kode' => self::KATEGORI_PULIH['kode']], [
            'nama' => self::KATEGORI_PULIH['nama'], 'tipe' => 'pemasukan', 'kode_akun_coa' => self::KATEGORI_PULIH['coa'], 'is_system' => true, 'is_active' => true, 'urutan' => 7,
        ]);
    }

    private function simpanFoto(StokRusak $r, string $isi): string
    {
        if (strlen($isi) > StokOpnameService::MAKS_FOTO) {
            throw new StokOpnameException('Foto maksimal 4 MB.');
        }
        $ext = ReturOnlineService::MIME_FOTO[(new \finfo(FILEINFO_MIME_TYPE))->buffer($isi)] ?? null;
        if (! $ext) {
            throw new StokOpnameException('Foto harus gambar JPG/PNG/WebP.');
        }
        $path = 'stok-rusak/' . now()->format('Ym') . '/rusak-' . $r->id . '-' . Str::random(10) . '.' . $ext;
        Storage::disk('local')->put($path, $isi);

        return $path;
    }
}
