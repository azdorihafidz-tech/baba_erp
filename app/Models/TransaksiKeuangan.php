<?php

namespace App\Models;

use App\Enums\KategoriPengeluaran;
use App\Enums\KategoriTransaksi;
use App\Enums\StatusSetoran;
use App\Enums\TipeTransaksiKeuangan;
use App\Traits\DijagaClosing;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransaksiKeuangan extends Model
{
    use HasFactory, HasCabang, HasAuditLog, SoftDeletes, FillsDeletedBy, DijagaClosing;

    protected $fillable = [
        'cabang_id',
        'kas_id',
        'nomor_transaksi',
        'tanggal_transaksi',
        'tipe',
        'kategori',
        'kategori_id',
        'kategori_pengeluaran',
        'keterangan',
        'jumlah',
        'bukti_path',
        'setoran_pair_id',
        'status_setoran',
        'alasan_tolak_setoran',
        'diterima_oleh_id',
        'waktu_diterima',
        'referensi_type',
        'referensi_id',
        'created_by',
        'catatan',
    ];

    // ===== Kunci Closing (DijagaClosing) =====
    protected function closingKolomTanggal(): string
    {
        return 'tanggal_transaksi';
    }

    protected function closingKonteks(): string
    {
        return 'Transaksi keuangan';
    }

    /**
     * Boleh berubah walau periode terkunci: status alur Transfer Dana
     * (terima/tolak dicatat di status, angka tidak berubah), link pasangan,
     * catatan/bukti, dan label kategori_pengeluaran (klasifikasi laporan
     * Setoran Harian, tidak mempengaruhi angka closing).
     */
    protected function closingKolomBebas(): array
    {
        return [
            'updated_at', 'status_setoran', 'alasan_tolak_setoran', 'diterima_oleh_id', 'waktu_diterima',
            'setoran_pair_id', 'referensi_id', 'catatan', 'bukti_path', 'kategori_pengeluaran',
        ];
    }

    protected function casts(): array
    {
        return [
            'tipe'              => TipeTransaksiKeuangan::class,
            'kategori'          => KategoriTransaksi::class,
            'kategori_pengeluaran' => KategoriPengeluaran::class,
            'status_setoran'    => StatusSetoran::class,
            'tanggal_transaksi' => 'date',
            'waktu_diterima'    => 'datetime',
            'jumlah'            => 'decimal:2',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function kas()
    {
        return $this->belongsTo(Kas::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function referensi()
    {
        return $this->morphTo('referensi');
    }

    public function kategoriDinamis()
    {
        return $this->belongsTo(\App\Models\KategoriTransaksi::class, 'kategori_id');
    }

    public function setoranPasangan()
    {
        return $this->belongsTo(TransaksiKeuangan::class, 'setoran_pair_id');
    }

    public function diterimaOleh()
    {
        return $this->belongsTo(User::class, 'diterima_oleh_id');
    }

    /** Label kategori: prefer dynamic, fallback ke enum lama */
    public function getLabelKategoriAttribute(): string
    {
        if ($this->kategori_id && $this->relationLoaded('kategoriDinamis') && $this->kategoriDinamis) {
            return $this->kategoriDinamis->nama;
        }
        return $this->kategori?->label() ?? '-';
    }

    /** Label kategori pengeluaran khusus laporan Setoran Harian — data lama (NULL) ditandai belum dikategorikan */
    public function getLabelKategoriPengeluaranAttribute(): string
    {
        if (!$this->kategori_pengeluaran) {
            return 'Lain-lain (belum dikategorikan)';
        }
        return $this->kategori_pengeluaran->label();
    }

    public function scopePemasukan($query)
    {
        return $query->where('tipe', TipeTransaksiKeuangan::Pemasukan);
    }

    public function scopePengeluaran($query)
    {
        return $query->where('tipe', TipeTransaksiKeuangan::Pengeluaran);
    }

    public function scopePeriode($query, string $periode)
    {
        return $query->where('tanggal_transaksi', 'like', $periode . '%');
    }
}
