<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shift extends Model
{
    use HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'nama_shift', 'cabang_id', 'jam_masuk', 'jam_keluar',
        'toleransi_telat_menit', 'is_active', 'deskripsi',
    ];

    protected $fillable = [
        'cabang_id',
        'nama_shift',
        'jam_masuk',
        'jam_keluar',
        'toleransi_telat_menit',
        'deskripsi',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'             => 'boolean',
            'toleransi_telat_menit' => 'integer',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function karyawans()
    {
        return $this->hasMany(Karyawan::class);
    }

    /** Label singkat untuk dropdown, contoh: "Shift 1 Pagi (08:00–16:00)" */
    public function getLabelAttribute(): string
    {
        $masuk  = substr($this->jam_masuk,  0, 5);
        $keluar = substr($this->jam_keluar, 0, 5);
        return "{$this->nama_shift} ({$masuk}–{$keluar})";
    }
}
