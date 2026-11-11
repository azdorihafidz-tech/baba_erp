<?php

namespace App\Models;

use App\Enums\TipeAssetMaintenance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetMaintenance extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'tanggal_maintenance',
        'jenis',
        'deskripsi',
        'biaya',
        'vendor_maintenance',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => TipeAssetMaintenance::class,
            'tanggal_maintenance' => 'date',
            'biaya' => 'decimal:2',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
