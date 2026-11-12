<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Barang dalam stok opname. qty_sistem dicatat saat barang dihitung; selisih = fisik − sistem. */
class StokOpnameItem extends Model
{
    protected $table = 'stok_opname_item';

    protected $fillable = [
        'stok_opname_id', 'item_id', 'satuan', 'qty_sistem', 'qty_fisik', 'qty_fisik_awal', 'hitung_ke', 'minta_hitung_ulang',
        'dihitung_oleh', 'dihitung_at', 'harga_pokok', 'nilai_selisih', 'catatan',
    ];

    protected function casts(): array
    {
        return ['qty_sistem' => 'float', 'qty_fisik' => 'float', 'qty_fisik_awal' => 'float', 'hitung_ke' => 'integer',
            'minta_hitung_ulang' => 'boolean', 'dihitung_at' => 'datetime', 'harga_pokok' => 'float', 'nilai_selisih' => 'float'];
    }

    public function opname()     { return $this->belongsTo(StokOpname::class, 'stok_opname_id'); }
    public function barang()     { return $this->belongsTo(Item::class, 'item_id'); }
    public function rincian()    { return $this->hasMany(StokOpnameRincian::class)->orderBy('id'); }
    public function penghitung() { return $this->belongsTo(User::class, 'dihitung_oleh'); }

    public function getSudahDihitungAttribute(): bool
    {
        return $this->qty_fisik !== null;
    }

    /** fisik − sistem (null bila belum dihitung). Dibulatkan 3 desimal supaya 0,1 + 0,2 tidak jadi selisih semu. */
    public function getSelisihAttribute(): ?float
    {
        return $this->qty_fisik === null ? null : round($this->qty_fisik - (float) $this->qty_sistem, 3);
    }
}
