<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pelanggan extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'kode_pelanggan', 'nama_pelanggan', 'telepon',
        'email', 'alamat', 'kota', 'is_active',
    ];

    protected $fillable = [
        'kode_pelanggan',
        'nama_pelanggan',
        'telepon',
        'email',
        'alamat',
        'kota',
        'catatan',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Total pembelian pelanggan — bug fix 2026-09-21: dulu ada 2 widget
     * beda definisi di halaman detail pelanggan ("Total Belanja" tanpa
     * filter status/tanggal, vs "Total Kg Giling" yang selalu 0 krn
     * D'mentai tidak pernah pakai basis kg). Diselaraskan jadi 1 angka:
     * cuma order yang SUDAH SELESAI dan tanggalnya sebelum hari ini
     * (bisnis retail D'mentai — order POS langsung lunas saat itu juga,
     * tidak ada konsep "pending lama").
     */
    public function getTotalPembelianAttribute(): float
    {
        return (float) $this->orders()
            ->where('status', 'selesai')
            ->whereDate('tanggal_order', '<', now()->toDateString())
            ->sum('total_bayar');
    }

    public function scopeAktif($q)
    {
        return $q->where('is_active', true);
    }
}
