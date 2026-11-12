<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sesi stok opname per lokasi (ERP BABA Tahap 7). Semua aturan di App\Services\StokOpnameService. */
class StokOpname extends Model
{
    protected $table = 'stok_opname';

    public const STATUS = ['hitung' => 'Sedang dihitung', 'diajukan' => 'Menunggu persetujuan', 'selesai' => 'Selesai', 'batal' => 'Dibatalkan'];
    public const WARNA = ['hitung' => 'info', 'diajukan' => 'warning', 'selesai' => 'success', 'batal' => 'secondary'];
    public const CAKUPAN = ['semua' => 'Semua barang', 'kategori' => 'Per kategori', 'barang' => 'Barang tertentu'];

    protected $fillable = [
        'nomor', 'cabang_id', 'cakupan', 'cakupan_ket', 'hitung_buta', 'status', 'catatan', 'dibuat_oleh', 'diajukan_oleh', 'diajukan_at',
        'disetujui_oleh', 'disetujui_at', 'alasan_tolak', 'dibatalkan_oleh', 'alasan_batal', 'ringkasan',
    ];

    protected function casts(): array
    {
        return ['hitung_buta' => 'boolean', 'diajukan_at' => 'datetime', 'disetujui_at' => 'datetime', 'ringkasan' => 'array'];
    }

    public function cabang()     { return $this->belongsTo(Cabang::class); }
    public function item()       { return $this->hasMany(StokOpnameItem::class); }
    public function log()        { return $this->hasMany(StokOpnameLog::class)->orderBy('id'); }
    public function pembuat()    { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function pengaju()    { return $this->belongsTo(User::class, 'diajukan_oleh'); }
    public function penyetuju()  { return $this->belongsTo(User::class, 'disetujui_oleh'); }
    public function pembatal()   { return $this->belongsTo(User::class, 'dibatalkan_oleh'); }

    public function getStatusLabelAttribute(): string { return self::STATUS[$this->status] ?? $this->status; }
    public function getStatusWarnaAttribute(): string { return self::WARNA[$this->status] ?? 'secondary'; }

    /** Toko Kemitraan / Toko Refill Member: stok & pembukuan milik mitra (disetujui pemilik tokonya, Owner BABA hanya melihat). */
    public function getMilikMitraAttribute(): bool
    {
        return in_array($this->cabang?->jenis_toko, ['kemitraan', 'toko_member'], true);
    }

    public function getBerjalanAttribute(): bool
    {
        return in_array($this->status, ['hitung', 'diajukan'], true);
    }
}
