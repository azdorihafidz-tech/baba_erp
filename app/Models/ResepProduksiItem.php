<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResepProduksiItem extends Model
{
    protected $fillable = ['resep_produksi_id', 'item_id', 'qty', 'satuan', 'urutan'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3'];
    }

    public function resep() { return $this->belongsTo(ResepProduksi::class, 'resep_produksi_id'); }
    public function bahan() { return $this->belongsTo(Item::class, 'item_id'); }
}
