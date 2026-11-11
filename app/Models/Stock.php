<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = ['item_id', 'lokasi_id', 'qty', 'qty_minimum'];

    public $timestamps = false;
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $fillable = [
        'item_id',
        'lokasi_id',
        'qty',
        'qty_minimum',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'qty_minimum' => 'decimal:3',
            'updated_at' => 'datetime',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function lokasi()
    {
        return $this->belongsTo(Cabang::class, 'lokasi_id');
    }

    public function isBelowMinimum(): bool
    {
        return $this->qty <= $this->qty_minimum;
    }

    public function scopeBelowMinimum($query)
    {
        return $query->whereColumn('qty', '<=', 'qty_minimum');
    }
}
