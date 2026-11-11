<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetDepreciation extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'periode',
        'nilai_buku_awal',
        'jumlah_penyusutan',
        'akumulasi_penyusutan',
        'nilai_buku_akhir',
        'produksi_aktual',
    ];

    protected function casts(): array
    {
        return [
            'nilai_buku_awal' => 'decimal:2',
            'jumlah_penyusutan' => 'decimal:2',
            'akumulasi_penyusutan' => 'decimal:2',
            'nilai_buku_akhir' => 'decimal:2',
            'produksi_aktual' => 'decimal:3',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
