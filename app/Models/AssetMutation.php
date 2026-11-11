<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'dari_lokasi_id',
        'ke_lokasi_id',
        'tanggal_mutasi',
        'alasan',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mutasi' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function dariLokasi()
    {
        return $this->belongsTo(Cabang::class, 'dari_lokasi_id');
    }

    public function keLokasi()
    {
        return $this->belongsTo(Cabang::class, 'ke_lokasi_id');
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
