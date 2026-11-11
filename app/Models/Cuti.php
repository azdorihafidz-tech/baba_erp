<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cuti extends Model
{
    use HasFactory, HasCabang, SoftDeletes, HasAuditLog, FillsDeletedBy;

    protected $fillable = [
        'karyawan_id',
        'cabang_id',
        'tipe',
        'tanggal_mulai',
        'tanggal_selesai',
        'jumlah_hari',
        'alasan',
        'status',
        'approved_by',
        'approved_at',
        'catatan_approver',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'  => 'date',
            'tanggal_selesai' => 'date',
            'approved_at'    => 'datetime',
        ];
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getLabelTipeAttribute(): string
    {
        return match($this->tipe) {
            'cuti_tahunan' => 'Cuti Tahunan',
            'izin'         => 'Izin',
            'sakit'        => 'Sakit',
            'cuti_khusus'  => 'Cuti Khusus',
            default        => $this->tipe,
        };
    }
}
