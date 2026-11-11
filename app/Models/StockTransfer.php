<?php

namespace App\Models;

use App\Enums\StatusTransfer;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'nomor_transfer',
        'dari_lokasi_id',
        'ke_lokasi_id',
        'tanggal_kirim',
        'tanggal_terima',
        'status',
        'stock_request_id',
        'catatan',
        'created_by',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusTransfer::class,
            'tanggal_kirim' => 'date',
            'tanggal_terima' => 'date',
        ];
    }

    public function dariLokasi()
    {
        return $this->belongsTo(Cabang::class, 'dari_lokasi_id');
    }

    public function keLokasi()
    {
        return $this->belongsTo(Cabang::class, 'ke_lokasi_id');
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
