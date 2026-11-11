<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tahap 2.5 D'mentai — pivot ketersediaan produk_jual/produk_tambahan per
 * outlet. Config murni (harga override + aktif/nonaktif per cabang), bukan
 * data transaksional -> tidak pakai SoftDeletes/HasAuditLog seperti model
 * lain, cukup hard delete cascade dari FK (lihat migration).
 */
class ItemCabang extends Model
{
    protected $table = 'item_cabang';

    protected $fillable = [
        'item_id',
        'cabang_id',
        'harga_override',
        'harga_beli',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'harga_override' => 'decimal:2',
            'harga_beli' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }
}
