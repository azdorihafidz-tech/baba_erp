<?php

namespace App\Models;

use App\Enums\StatusStockRequest;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockRequest extends Model
{
    use HasFactory, HasCabang, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'cabang_id',
        'nomor_request',
        'tanggal_request',
        'status',
        'approved_by',
        'approved_at',
        'catatan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusStockRequest::class,
            'tanggal_request' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function items()
    {
        return $this->hasMany(StockRequestItem::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transfers()
    {
        return $this->hasMany(StockTransfer::class);
    }
}
