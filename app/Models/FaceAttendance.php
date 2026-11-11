<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

class FaceAttendance extends Model
{
    use HasAuditLog;

    public $timestamps = false;

    protected $fillable = [
        'karyawan_id',
        'cabang_id',
        'tipe',
        'waktu',
        'foto_absen',
        'confidence_score',
        'latitude',
        'longitude',
        'jarak_dari_cabang',
        'device_id',
        'status',
        'is_liveness_passed',
        'keterangan',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'waktu'              => 'datetime',
            'created_at'        => 'datetime',
            'confidence_score'  => 'decimal:2',
            'latitude'          => 'decimal:8',
            'longitude'         => 'decimal:8',
            'is_liveness_passed' => 'boolean',
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

    public function device()
    {
        return $this->belongsTo(AbsenDevice::class, 'device_id');
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'valid');
    }

    public function scopeHariIni($query)
    {
        return $query->whereDate('waktu', today());
    }

    public function scopeClockIn($query)
    {
        return $query->where('tipe', 'clock_in');
    }

    public function scopeClockOut($query)
    {
        return $query->where('tipe', 'clock_out');
    }
}
