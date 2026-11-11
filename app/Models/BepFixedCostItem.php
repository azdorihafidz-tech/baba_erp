<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BepFixedCostItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bep_setting_id',
        'nama_komponen',
        'kategori',
        'jumlah',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
        ];
    }

    public function bepSetting()
    {
        return $this->belongsTo(BepSetting::class);
    }
}
