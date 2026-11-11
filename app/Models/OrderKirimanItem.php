<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 4.49 Tahap B2 — rincian barang Kirim ke Cabang per baris order (porting
 * erp-coffeeshop). `item_id` = barang gudang yang keluar; `item_tujuan_id` =
 * barang milik cabang tujuan (padanan Kode Item, data per cabang 4.39) yang
 * stoknya bertambah saat Terima.
 */
class OrderKirimanItem extends Model
{
    protected $fillable = ['order_id', 'order_item_id', 'item_id', 'item_tujuan_id', 'qty_kirim', 'hpp_kirim', 'qty_terima', 'nilai_terima', 'qty_retur'];

    protected function casts(): array
    {
        return [
            'qty_kirim'    => 'decimal:3',
            'hpp_kirim'    => 'decimal:2',
            'qty_terima'   => 'decimal:3',
            'nilai_terima' => 'decimal:2',
            'qty_retur'    => 'decimal:3',
        ];
    }

    public function order()      { return $this->belongsTo(Order::class); }
    public function orderItem()  { return $this->belongsTo(OrderItem::class); }
    public function item()       { return $this->belongsTo(Item::class); }
    public function itemTujuan() { return $this->belongsTo(Item::class, 'item_tujuan_id'); }

    /** Modal gudang per unit (dipakai saat kiriman kurang, ditolak, atau retur dengan uang kembali). */
    public function getHppPerUnitAttribute(): float
    {
        return (float) $this->qty_kirim > 0 ? round((float) $this->hpp_kirim / (float) $this->qty_kirim, 2) : 0.0;
    }

    /** Harga yang dibayar cabang per unit. */
    public function getHargaTerimaPerUnitAttribute(): float
    {
        return (float) $this->qty_terima > 0 ? round((float) $this->nilai_terima / (float) $this->qty_terima, 2) : 0.0;
    }
}
