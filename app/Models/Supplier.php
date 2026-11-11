<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'kode_supplier',
        'nama_supplier',
        'kontak_person',
        'telepon',
        'email',
        'alamat',
        'kota',
        'cabang_id', // Tahap C 4.38 — supplier internal (lokasi kita, mis. Gudang Pusat)
        'catatan',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** Lokasi kita kalau supplier ini internal (mis. Gudang Pusat). */
    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function isInternal(): bool
    {
        return $this->cabang_id !== null;
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
