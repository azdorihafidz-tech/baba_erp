<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory, HasAuditLog;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'item_id',
        'item_variant_id',
        'nama_item',
        'qty',
        'satuan',
        'harga_satuan',
        'total_harga',
        'hpp',
        'berat_daging',
        'jenis_olahan',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'qty'         => 'decimal:3',
            'harga_satuan'=> 'decimal:2',
            'total_harga' => 'decimal:2',
            'hpp'         => 'decimal:2',
            'berat_daging'=> 'decimal:3',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /** Tahap 3 D'mentai — varian yang terjual (nullable, item tanpa varian). */
    public function variant()
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }
}
