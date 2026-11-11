<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nilai untuk 1 ItemAttribute — contoh: atribut "Size" punya values "S"/"M"/"L".
 */
class ItemAttributeValue extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'item_attribute_id',
        'nilai',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    public function attribute()
    {
        return $this->belongsTo(ItemAttribute::class, 'item_attribute_id');
    }

    public function variants()
    {
        return $this->belongsToMany(ItemVariant::class, 'item_variant_values');
    }
}
