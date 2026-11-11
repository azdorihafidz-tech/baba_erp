<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturInternalItem extends Model
{
    protected $fillable = ['retur_internal_id', 'order_kiriman_item_id', 'item_id', 'qty', 'harga_per_unit', 'hpp_per_unit', 'hpp_cabang'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'harga_per_unit' => 'decimal:2', 'hpp_per_unit' => 'decimal:2', 'hpp_cabang' => 'decimal:2'];
    }

    public function retur()        { return $this->belongsTo(ReturInternal::class, 'retur_internal_id'); }
    public function kirimanItem()  { return $this->belongsTo(OrderKirimanItem::class, 'order_kiriman_item_id'); }
    public function item()         { return $this->belongsTo(Item::class); }

    public function getNilaiAttribute(): float
    {
        return round((float) $this->qty * (float) $this->harga_per_unit, 2);
    }
}
