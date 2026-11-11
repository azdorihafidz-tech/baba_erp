<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesananOnlineItem extends Model
{
    protected $table = 'pesanan_online_item';

    protected $fillable = ['pesanan_online_id', 'produk_online_id', 'item_id', 'nama', 'satuan', 'qty', 'harga', 'dasar_harga', 'subtotal', 'hpp', 'qty_retur'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'qty_retur' => 'integer', 'harga' => 'decimal:2', 'subtotal' => 'decimal:2', 'hpp' => 'decimal:2'];
    }

    public function pesanan() { return $this->belongsTo(PesananOnline::class, 'pesanan_online_id'); }
    public function produk()  { return $this->belongsTo(ProdukOnline::class, 'produk_online_id'); }
    public function barang()  { return $this->belongsTo(Item::class, 'item_id'); }
}
