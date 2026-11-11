<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoyaltyProgram extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, FillsDeletedBy;

    protected $fillable = [
        'nama',
        'tipe_program',
        'deskripsi',
        'target_qty_kg',
        'satuan_qty',
        'sumber_data',
        'tipe_item',
        'periode_mulai',
        'periode_akhir',
        'hadiah',
        'nominal_voucher',
        'berulang',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_qty_kg'   => 'decimal:2',
            'nominal_voucher' => 'decimal:2',
            'periode_mulai'   => 'date',
            'periode_akhir'   => 'date',
            'berulang'        => 'boolean',
        ];
    }

    public function pencapaian()
    {
        return $this->hasMany(LoyaltyPencapaian::class);
    }

    public function klaims()
    {
        return $this->hasMany(LoyaltyKlaim::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    public function scopeEventBased($query)
    {
        return $query->where('tipe_program', 'event_based');
    }

    public function scopeAutoTrack($query)
    {
        return $query->where('tipe_program', 'auto_track');
    }
}
