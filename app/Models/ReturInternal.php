<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 4.49 Tahap B3 — Retur cabang → Gudang (porting erp-coffeeshop). */
class ReturInternal extends Model
{
    public const ALASAN = ['basi' => 'Basi', 'rusak' => 'Rusak', 'kelebihan' => 'Kelebihan kirim', 'lainnya' => 'Lainnya'];

    protected $fillable = [
        'kode_retur', 'order_id', 'dari_cabang_id', 'ke_cabang_id', 'alasan', 'catatan', 'uang_kembali', 'nilai',
        'status', 'dibuat_by', 'diproses_by', 'diproses_at', 'alasan_tolak',
    ];

    protected function casts(): array
    {
        return ['uang_kembali' => 'boolean', 'nilai' => 'decimal:2', 'diproses_at' => 'datetime'];
    }

    public function order()        { return $this->belongsTo(Order::class); }
    public function dariCabang()   { return $this->belongsTo(Cabang::class, 'dari_cabang_id'); }
    public function keCabang()     { return $this->belongsTo(Cabang::class, 'ke_cabang_id'); }
    public function items()        { return $this->hasMany(ReturInternalItem::class); }
    public function dibuatOleh()   { return $this->belongsTo(User::class, 'dibuat_by'); }
    public function diprosesOleh() { return $this->belongsTo(User::class, 'diproses_by'); }
    public function orderKoreksi() { return $this->hasOne(Order::class, 'retur_internal_id'); }

    public function getAlasanLabelAttribute(): string
    {
        return self::ALASAN[$this->alasan] ?? (string) $this->alasan;
    }
}
