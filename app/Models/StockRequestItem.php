<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockRequestItem extends Model
{
    use HasFactory, HasAuditLog;

    public $timestamps = false;

    protected $fillable = [
        'stock_request_id',
        'item_id',
        'qty_diminta',
        'qty_disetujui',
        'qty_diterima',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'qty_diminta' => 'decimal:3',
            'qty_disetujui' => 'decimal:3',
            'qty_diterima' => 'decimal:3',
        ];
    }

    public function stockRequest()
    {
        return $this->belongsTo(StockRequest::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
