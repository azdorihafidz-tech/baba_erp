<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kas extends Model
{
    use HasFactory, HasCabang, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'cabang_id',
        'nama_kas',
        'tipe_kas',
        'default_untuk',
        'nomor_rekening',
        'nama_bank',
        'saldo_awal',
        'saldo_sekarang',
        'saldo_minimum',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'saldo_awal'     => 'decimal:2',
            'saldo_sekarang' => 'decimal:2',
            'saldo_minimum'  => 'decimal:2',
            'is_active'      => 'boolean',
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function transaksis()
    {
        return $this->hasMany(TransaksiKeuangan::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
