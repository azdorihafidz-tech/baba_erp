<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoyaltyPencapaian extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, FillsDeletedBy;

    protected $table = 'loyalty_pencapaian';

    protected $fillable = [
        'pelanggan_id',
        'loyalty_program_id',
        'tanggal_tercapai',
        'progress_kg',
        'status',
        'catatan_hadiah',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_tercapai' => 'date',
            'progress_kg'      => 'decimal:2',
        ];
    }

    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function loyaltyProgram()
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }
}
