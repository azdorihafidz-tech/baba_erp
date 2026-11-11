<?php

namespace App\Traits;

use App\Models\Cabang;
use App\Models\Scopes\CabangScope;

trait HasCabang
{
    /**
     * Boot the trait - daftarkan global scope
     */
    public static function bootHasCabang(): void
    {
        // Auto set cabang_id saat create jika belum diset
        static::creating(function ($model) {
            if (empty($model->cabang_id) && auth()->check()) {
                $model->cabang_id = session('active_cabang_id')
                    ?? auth()->user()->defaultCabangId();
            }
        });
    }

    /**
     * Daftarkan CabangScope sebagai global scope
     */
    public static function addCabangScope(): void
    {
        static::addGlobalScope(new CabangScope());
    }

    /**
     * Relasi ke cabang
     */
    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    /**
     * Scope untuk filter manual per cabang
     */
    public function scopeForCabang($query, int $cabangId)
    {
        return $query->where($this->getTable() . '.cabang_id', $cabangId);
    }

    /**
     * Scope tanpa filter cabang (untuk laporan konsolidasi)
     */
    public function scopeAllCabang($query)
    {
        return $query->withoutGlobalScope(CabangScope::class);
    }
}
