<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Atribut varian milik 1 Item (scoped per-item, bukan taxonomy global) —
 * contoh: "Size", "Rasa", "Level Pedas". UI dikerjakan di Tahap 3.
 */
class ItemAttribute extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'item_id',
        'nama',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function values()
    {
        return $this->hasMany(ItemAttributeValue::class)->orderBy('urutan');
    }
}
