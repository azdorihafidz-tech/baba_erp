<?php

namespace App\Models;

use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BepSetting extends Model
{
    use HasFactory, HasCabang;

    protected $fillable = [
        'cabang_id',
        'periode',
        'total_biaya_tetap',
        'catatan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_biaya_tetap' => 'decimal:2',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function fixedCostItems()
    {
        return $this->hasMany(BepFixedCostItem::class);
    }

    public function products()
    {
        return $this->hasMany(BepProduct::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
