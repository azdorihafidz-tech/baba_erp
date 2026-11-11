<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockTransferItem extends Model
{
    use HasFactory, HasAuditLog;

    public $timestamps = false;

    protected $fillable = [
        'stock_transfer_id',
        'item_id',
        'qty_kirim',
        'qty_terima',
        'harga_per_unit',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'qty_kirim' => 'decimal:3',
            'qty_terima' => 'decimal:3',
        ];
    }

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
