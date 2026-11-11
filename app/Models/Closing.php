<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot closing (harian / bulanan / keseluruhan). Lihat
 * ClosingService & ClosingLock. Tidak pakai SoftDeletes — closing tidak
 * pernah dihapus, hanya "dibuka" (status) lalu dibuat versi baru.
 */
class Closing extends Model
{
    public const STATUS_FINAL = 'final';
    public const STATUS_DIBUKA = 'dibuka';

    protected $fillable = [
        'jenis', 'cabang_id', 'periode_mulai', 'periode_selesai', 'label_periode', 'versi', 'status',
        'omzet', 'hpp', 'laba_bersih', 'saldo_kas_akhir', 'nilai_stok', 'total_selisih_fisik',
        'data', 'catatan', 'ditutup_oleh', 'ditutup_pada', 'dibuka_oleh', 'dibuka_pada', 'alasan_buka',
    ];

    protected function casts(): array
    {
        return [
            'periode_mulai'   => 'date',
            'periode_selesai' => 'date',
            'ditutup_pada'    => 'datetime',
            'dibuka_pada'     => 'datetime',
            'data'            => 'array',
            'omzet'           => 'decimal:2',
            'hpp'             => 'decimal:2',
            'laba_bersih'     => 'decimal:2',
            'saldo_kas_akhir' => 'decimal:2',
            'nilai_stok'      => 'decimal:2',
            'total_selisih_fisik' => 'decimal:2',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function kasRows()
    {
        return $this->hasMany(ClosingKas::class);
    }

    public function ditutupOleh()
    {
        return $this->belongsTo(User::class, 'ditutup_oleh');
    }

    public function dibukaOleh()
    {
        return $this->belongsTo(User::class, 'dibuka_oleh');
    }

    public function scopeFinal($query)
    {
        return $query->where('status', self::STATUS_FINAL);
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function isKonsolidasi(): bool
    {
        return $this->cabang_id === null;
    }

    public function getJenisLabelAttribute(): string
    {
        if ($this->isKonsolidasi()) {
            return 'Tutup Buku Keseluruhan';
        }

        return $this->jenis === 'harian' ? 'Closing Harian' : 'Tutup Buku Bulanan';
    }

    public function getNamaCabangAttribute(): string
    {
        return $this->cabang?->nama_cabang ?? 'Semua Cabang';
    }
}
