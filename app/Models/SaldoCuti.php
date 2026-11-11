<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaldoCuti extends Model
{
    use HasFactory;

    protected $fillable = [
        'karyawan_id',
        'tahun',
        'saldo_awal',
        'terpakai',
        'sisa',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function getSisaAttribute(): int
    {
        return $this->saldo_awal - $this->terpakai;
    }
}
