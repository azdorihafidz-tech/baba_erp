<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

/** Pengaturan Toko Online (satu baris). */
class TokoOnlinePengaturan extends Model
{
    use HasAuditLog;

    protected $table = 'toko_online_pengaturan';

    protected $fillable = ['hari_terima_otomatis', 'jam_batas_bayar'];

    protected function casts(): array
    {
        return ['hari_terima_otomatis' => 'integer', 'jam_batas_bayar' => 'integer'];
    }

    public static function ambil(): self
    {
        return static::query()->orderBy('id')->first() ?? static::create(['hari_terima_otomatis' => 7, 'jam_batas_bayar' => 48]);
    }
}
