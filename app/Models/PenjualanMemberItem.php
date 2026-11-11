<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenjualanMemberItem extends Model
{
    protected $table = 'penjualan_member_item';

    protected $fillable = ['penjualan_member_id', 'item_id', 'nama', 'kategori', 'qty', 'harga', 'subtotal', 'modal'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'harga' => 'decimal:2', 'subtotal' => 'decimal:2', 'modal' => 'decimal:2'];
    }

    public function penjualan() { return $this->belongsTo(PenjualanMember::class, 'penjualan_member_id'); }
}
