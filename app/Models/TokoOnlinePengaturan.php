<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

/** Pengaturan Toko Online (satu baris). */
class TokoOnlinePengaturan extends Model
{
    use HasAuditLog;

    protected $table = 'toko_online_pengaturan';

    /** Tahap 2f: saklar toko untuk member (diatur Owner). */
    public const STATUS_TOKO = [
        'tutup' => 'Tutup — menu tampil “Segera hadir”',
        'uji'   => 'Uji coba — hanya akun yang dipilih',
        'buka'  => 'Buka — semua member',
    ];

    protected $fillable = ['status_toko', 'akun_uji', 'hari_terima_otomatis', 'jam_batas_bayar', 'hari_batas_retur'];

    protected function casts(): array
    {
        return ['hari_terima_otomatis' => 'integer', 'jam_batas_bayar' => 'integer', 'hari_batas_retur' => 'integer', 'akun_uji' => 'array'];
    }

    public static function ambil(): self
    {
        return static::query()->orderBy('id')->first() ?? static::create(['hari_terima_otomatis' => 7, 'jam_batas_bayar' => 48]);
    }

    /** Toko terbuka untuk akun ini? */
    public function bukaUntuk(?int $memberId): bool
    {
        return match ($this->status_toko) {
            'buka'  => true,
            'uji'   => $memberId !== null && in_array($memberId, array_map('intval', (array) $this->akun_uji), true),
            default => false,
        };
    }
}
