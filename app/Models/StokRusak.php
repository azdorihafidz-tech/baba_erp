<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Barang rusak hasil stok opname yang ditahan sampai ditindaklanjuti (ERP BABA Tahap 7c). Aturan di StokRusakService. */
class StokRusak extends Model
{
    protected $table = 'stok_rusak';

    protected $fillable = ['cabang_id', 'item_id', 'stok_opname_id', 'stok_opname_rincian_id', 'satuan', 'qty', 'qty_sisa', 'harga_pokok', 'status', 'catatan'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'qty_sisa' => 'float', 'harga_pokok' => 'float'];
    }

    public function cabang()   { return $this->belongsTo(Cabang::class); }
    public function barang()   { return $this->belongsTo(Item::class, 'item_id'); }
    public function opname()   { return $this->belongsTo(StokOpname::class, 'stok_opname_id'); }
    public function tindakan() { return $this->hasMany(StokRusakTindakan::class)->orderBy('id'); }

    public function getNilaiSisaAttribute(): float
    {
        return round($this->qty_sisa * $this->harga_pokok, 2);
    }

    /** Lama ditahan (hari sejak opname disetujui / dicatat). */
    public function getUmurHariAttribute(): int
    {
        return (int) $this->created_at?->startOfDay()->diffInDays(now()->startOfDay());
    }
}
