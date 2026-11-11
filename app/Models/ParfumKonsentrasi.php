<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konsentrasi parfum (EDP, EDT, …): persen bibit & fixative dari volume cairan, sisanya alkohol,
 * dan lama pemeraman. Dipakai "Buat Parfum" untuk membuat resep racik cairan.
 */
class ParfumKonsentrasi extends Model
{
    protected $table = 'parfum_konsentrasi';

    protected $fillable = ['nama', 'keterangan', 'persen_bibit', 'persen_fixative', 'lama_peram_hari', 'is_active', 'urutan'];

    protected function casts(): array
    {
        return [
            'persen_bibit'    => 'decimal:2',
            'persen_fixative' => 'decimal:2',
            'lama_peram_hari' => 'integer',
            'is_active'       => 'boolean',
        ];
    }

    /** % alkohol = sisa dari 100%. */
    public function getPersenAlkoholAttribute(): float
    {
        return round(100 - (float) $this->persen_bibit - (float) $this->persen_fixative, 2);
    }

    public function scopeAktif($q)
    {
        return $q->where('is_active', true);
    }
}
