<?php

namespace App\Models;

use App\Enums\TipePembayaran;
use Illuminate\Database\Eloquent\Model;

class SetoranDetail extends Model
{
    protected $fillable = ['setoran_id', 'metode', 'jumlah_sistem', 'jumlah_fisik'];

    protected function casts(): array
    {
        return [
            'metode' => TipePembayaran::class,
            'jumlah_sistem' => 'decimal:2',
            'jumlah_fisik' => 'decimal:2',
        ];
    }

    public function setoran()
    {
        return $this->belongsTo(Setoran::class);
    }
}
