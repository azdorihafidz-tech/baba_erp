<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClosingKas extends Model
{
    protected $table = 'closing_kas';

    protected $fillable = [
        'closing_id', 'kas_id', 'nama_kas', 'nama_cabang', 'grup',
        'saldo_awal', 'masuk', 'keluar', 'mutasi_masuk', 'mutasi_keluar', 'saldo_akhir',
        'saldo_fisik', 'selisih',
    ];

    protected function casts(): array
    {
        return [
            'saldo_awal'    => 'decimal:2',
            'masuk'         => 'decimal:2',
            'keluar'        => 'decimal:2',
            'mutasi_masuk'  => 'decimal:2',
            'mutasi_keluar' => 'decimal:2',
            'saldo_akhir'   => 'decimal:2',
            'saldo_fisik'   => 'decimal:2',
            'selisih'       => 'decimal:2',
        ];
    }

    public function closing()
    {
        return $this->belongsTo(Closing::class);
    }
}
