<?php

namespace App\Models;

use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BepReport extends Model
{
    use HasFactory, HasCabang;

    protected $fillable = [
        'cabang_id',
        'periode',
        'total_biaya_tetap',
        'total_biaya_variabel',
        'total_pendapatan',
        'bep_tercapai',
        'selisih_dari_bep',
        'persentase_bep',
    ];

    protected function casts(): array
    {
        return [
            'bep_tercapai' => 'boolean',
            'total_biaya_tetap' => 'decimal:2',
            'total_biaya_variabel' => 'decimal:2',
            'total_pendapatan' => 'decimal:2',
            'selisih_dari_bep' => 'decimal:2',
            'persentase_bep' => 'decimal:2',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }
}
