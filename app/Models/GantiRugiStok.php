<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ganti rugi karyawan atas barang hilang di stok opname (ERP BABA Tahap 7d). Aturan di GantiRugiStokService. */
class GantiRugiStok extends Model
{
    protected $table = 'ganti_rugi_stok';

    public const CARA = ['tunai' => 'Bayar tunai', 'potong_gaji' => 'Potong gaji'];
    public const STATUS = ['berjalan' => 'Berjalan', 'lunas' => 'Lunas', 'batal' => 'Dibatalkan'];
    public const WARNA = ['berjalan' => 'warning', 'lunas' => 'success', 'batal' => 'secondary'];

    protected $fillable = [
        'nomor', 'stok_opname_id', 'stok_opname_rincian_id', 'cabang_id', 'karyawan_id', 'nilai', 'nilai_pokok', 'alasan', 'cara', 'cicilan_bulan',
        'mulai_periode', 'terbayar', 'status', 'catatan', 'dibuat_oleh', 'dibatalkan_oleh', 'alasan_batal',
    ];

    protected function casts(): array
    {
        return ['nilai' => 'float', 'nilai_pokok' => 'float', 'terbayar' => 'float', 'cicilan_bulan' => 'integer'];
    }

    public function karyawan() { return $this->belongsTo(Karyawan::class); }
    public function cabang()   { return $this->belongsTo(Cabang::class); }
    public function opname()   { return $this->belongsTo(StokOpname::class, 'stok_opname_id'); }
    public function rincian()  { return $this->belongsTo(StokOpnameRincian::class, 'stok_opname_rincian_id'); }
    public function bayar()    { return $this->hasMany(GantiRugiStokBayar::class)->orderBy('id'); }
    public function pembuat()  { return $this->belongsTo(User::class, 'dibuat_oleh'); }

    public function getSisaAttribute(): float
    {
        return round(max(0, $this->nilai - $this->terbayar), 2);
    }

    /** Cicilan per bulan (pembulatan ke atas ke rupiah). */
    public function getCicilanAttribute(): float
    {
        return (float) ceil($this->nilai / max(1, $this->cicilan_bulan));
    }

    public function getStatusLabelAttribute(): string { return self::STATUS[$this->status] ?? $this->status; }
    public function getStatusWarnaAttribute(): string { return self::WARNA[$this->status] ?? 'secondary'; }
}
