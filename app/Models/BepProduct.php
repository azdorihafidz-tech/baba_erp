<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BepProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'bep_setting_id',
        'nama_produk',
        'tipe',
        'harga_jual_per_unit',
        'biaya_variabel_per_unit',
        'margin_kontribusi',
        'bep_unit',
        'bep_rupiah',
        'target_penjualan_unit',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'harga_jual_per_unit' => 'decimal:2',
            'biaya_variabel_per_unit' => 'decimal:2',
            'margin_kontribusi' => 'decimal:2',
            'bep_unit' => 'decimal:3',
            'bep_rupiah' => 'decimal:2',
            'target_penjualan_unit' => 'decimal:3',
        ];
    }

    public function bepSetting()
    {
        return $this->belongsTo(BepSetting::class);
    }

    public function hitungBep(): void
    {
        $this->margin_kontribusi = $this->harga_jual_per_unit - $this->biaya_variabel_per_unit;

        if ($this->margin_kontribusi > 0 && $this->bepSetting) {
            $totalBiayaTetap = $this->bepSetting->total_biaya_tetap;
            $this->bep_unit = $totalBiayaTetap / $this->margin_kontribusi;
            $rasioMc = $this->margin_kontribusi / $this->harga_jual_per_unit;
            $this->bep_rupiah = $rasioMc > 0 ? $totalBiayaTetap / $rasioMc : 0;
        }
    }
}
