<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderItem extends Model
{
    use HasFactory, HasAuditLog;

    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'qty_pesan',
        'qty_terima',
        'harga_satuan',
        'total_harga',
        'catatan',
        // Sprint Unit Family (2026-09-27): audit trail input asli user
        // sebelum konversi ke unit pakai. NULL berarti input dlm unit pakai
        // (legacy/backward compat).
        'unit_input',
        'qty_input',
    ];

    protected function casts(): array
    {
        return [
            'qty_pesan' => 'decimal:3',
            'qty_terima' => 'decimal:3',
            'harga_satuan' => 'decimal:2',
            'total_harga' => 'decimal:2',
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
