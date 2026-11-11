<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AbsenDevice extends Model
{
    use HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'cabang_id',
        'device_name',
        'device_token',
        'is_active',
        'registered_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'registered_at' => 'datetime',
            'last_used_at'  => 'datetime',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function faceAttendances()
    {
        return $this->hasMany(FaceAttendance::class, 'device_id');
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('device_token', $token)->exists());

        return $token;
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
