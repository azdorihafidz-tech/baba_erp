<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockBatch extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, FillsDeletedBy;

    protected $fillable = [
        'item_id',
        'lokasi_id',
        'referensi_type',
        'referensi_id',
        'qty_awal',
        'qty_sisa',
        'harga_beli_per_unit',
        'tanggal_masuk',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'qty_awal'            => 'decimal:3',
            'qty_sisa'            => 'decimal:3',
            'harga_beli_per_unit' => 'decimal:2',
            'tanggal_masuk'       => 'date',
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

    public function scopeAktif($query)
    {
        return $query->where('qty_sisa', '>', 0);
    }

    public function scopeUntukLokasi($query, int $lokasiId)
    {
        return $query->where('lokasi_id', $lokasiId);
    }
}
