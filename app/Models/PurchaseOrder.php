<?php

namespace App\Models;

use App\Enums\StatusPurchaseOrder;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, HasCabang, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'cabang_id',
        'supplier_id',
        'nomor_po',
        'tanggal_po',
        'tanggal_terima',
        'tanggal_kirim',
        'status',
        'pembelian_langsung',
        'total_harga',
        'alasan_langsung',
        'catatan',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPurchaseOrder::class,
            'pembelian_langsung' => 'boolean',
            'tanggal_po' => 'date',
            'tanggal_terima' => 'date',
            'tanggal_kirim' => 'datetime',
            'approved_at' => 'datetime',
            'total_harga' => 'decimal:2',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** Tahap C 4.38 — nota POS gudang (supplier internal) yang memproses PO ini. */
    public function ordersGudang()
    {
        return $this->hasMany(Order::class, 'purchase_order_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
