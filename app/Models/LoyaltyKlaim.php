<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoyaltyKlaim extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, FillsDeletedBy;

    protected $fillable = [
        'loyalty_program_id',
        'pelanggan_id',
        'order_id',
        'bukti_url',
        'bukti_catatan',
        'status',
        'nominal_voucher',
        'approved_by_user_id',
        'approved_at',
        'rejected_reason',
        'issued_at',
        'catatan_issued',
    ];

    protected function casts(): array
    {
        return [
            'nominal_voucher' => 'decimal:2',
            'approved_at'     => 'datetime',
            'issued_at'       => 'datetime',
        ];
    }

    public function loyaltyProgram()
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
