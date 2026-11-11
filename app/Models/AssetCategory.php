<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AssetCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_kategori',
        'nama_kategori',
        'deskripsi',
    ];

    public function assets()
    {
        return $this->hasMany(Asset::class, 'kategori_aset_id');
    }
}
