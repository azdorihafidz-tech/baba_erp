<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturOnlineItem extends Model
{
    protected $table = 'retur_online_item';

    protected $fillable = ['retur_online_id', 'pesanan_online_item_id', 'item_id', 'nama', 'qty', 'harga', 'subtotal'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'harga' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function retur()       { return $this->belongsTo(ReturOnline::class, 'retur_online_id'); }
    public function pesananItem() { return $this->belongsTo(PesananOnlineItem::class, 'pesanan_online_item_id'); }
}
