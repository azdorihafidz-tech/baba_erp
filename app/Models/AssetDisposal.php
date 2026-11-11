<?php

namespace App\Models;

use App\Enums\TipeAssetDisposal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetDisposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'tanggal_disposal',
        'tipe',
        'nilai_jual',
        'nilai_buku_saat_disposal',
        'keuntungan_kerugian',
        'pembeli',
        'catatan',
        'approved_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => TipeAssetDisposal::class,
            'tanggal_disposal' => 'date',
            'nilai_jual' => 'decimal:2',
            'nilai_buku_saat_disposal' => 'decimal:2',
            'keuntungan_kerugian' => 'decimal:2',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
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
