<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 1 kombinasi nilai atribut untuk 1 Item (mis. Size M + Rasa Mentai).
 *
 * CATATAN ARSITEKTUR (Tahap 2, 2026-09-13): kolom `stok` MURNI PLACEHOLDER,
 * belum terintegrasi ke sistem stok FIFO (`stocks`/`stock_batches`/
 * `stock_movements`) — integrasi nyata diputuskan di Tahap 3/4. Kolom
 * `resep_override` juga RESERVED (JSON, format belum diputuskan), belum
 * dibaca/ditulis logic manapun.
 */
class ItemVariant extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'item_id',
        'sku',
        'harga_override',
        'stok',
        'resep_override',
        'is_active',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'harga_override' => 'decimal:2',
            'stok'            => 'integer',
            'resep_override'  => 'array',
            'is_active'       => 'boolean',
            'urutan'          => 'integer',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function attributeValues()
    {
        return $this->belongsToMany(ItemAttributeValue::class, 'item_variant_values');
    }

    /** Harga efektif varian ini: harga_override kalau diisi, fallback ke harga_jual item induk. */
    public function getHargaEfektifAttribute(): float
    {
        return (float) ($this->harga_override ?? $this->item?->harga_jual ?? 0);
    }

    /**
     * Harga efektif varian di 1 cabang: harga_override varian kalau diisi,
     * else harga jual induk DI CABANG ITU (harga per outlet). Tanpa ini
     * varian tanpa override selalu memakai harga jual default walau outlet
     * punya harga khusus (CLAUDE.md 4.38).
     */
    public function hargaEfektifDiCabang(?int $cabangId): float
    {
        if ($this->harga_override !== null) {
            return (float) $this->harga_override;
        }

        return $cabangId && $this->item
            ? $this->item->hargaEfektifDiCabang($cabangId)
            : (float) ($this->item?->harga_jual ?? 0);
    }

    /** Label gabungan nilai atribut, mis. "M / Mentai" — dipakai UI Tahap 3. */
    public function getLabelAttribute(): string
    {
        return $this->attributeValues->pluck('nilai')->implode(' / ');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
