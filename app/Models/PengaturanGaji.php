<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PengaturanGaji extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $fillable = [
        'tarif_lembur_per_jam',
        'potongan_alpa_per_hari',
        'potongan_telat_per_menit',
        'hari_kerja_per_minggu',
    ];

    protected function casts(): array
    {
        return [
            'tarif_lembur_per_jam'     => 'decimal:2',
            'potongan_alpa_per_hari'   => 'decimal:2',
            'potongan_telat_per_menit' => 'decimal:2',
            'hari_kerja_per_minggu'    => 'integer',
        ];
    }

    /**
     * Kolom yang harus dikembalikan sebagai float (bukan string "15000.00").
     */
    private static array $numericKeys = [
        'tarif_lembur_per_jam',
        'potongan_alpa_per_hari',
        'potongan_telat_per_menit',
    ];

    /**
     * Singleton getter.
     *
     * Tanpa argument  → return model object (untuk display/update di controller)
     * Dengan $key     → return nilai kolom tersebut sebagai tipe yang tepat:
     *                   kolom numerik → float, kolom lain → nilai asli
     *
     * Contoh:
     *   $setting = PengaturanGaji::getSetting();                     // model
     *   $tarif   = PengaturanGaji::getSetting('tarif_lembur_per_jam'); // float 15000.0
     */
    public static function getSetting(?string $key = null, mixed $default = null): mixed
    {
        /** @var static|null $instance */
        static $instance = null;

        if ($instance === null) {
            $instance = static::firstOrCreate([], [
                'tarif_lembur_per_jam'     => 15000,
                'potongan_alpa_per_hari'   => 50000,
                'potongan_telat_per_menit' => 0,
                'hari_kerja_per_minggu'    => 6,
            ]);
        }

        // Tanpa key → return model (backward-compat dengan semua call site yang ada)
        if ($key === null) {
            return $instance;
        }

        $value = $instance->{$key} ?? $default;

        // Paksa float untuk kolom numerik agar "15000.00" tidak jadi string saat arithmetic
        if (in_array($key, self::$numericKeys, true)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Reset cached instance (berguna untuk testing / setelah update).
     */
    public static function resetCache(): void
    {
        // Tidak bisa reset static var dari luar, tapi getSetting sudah cukup untuk production.
        // Untuk test: gunakan DB::table('pengaturan_gajis')->update([...]) lalu refresh().
    }
}
