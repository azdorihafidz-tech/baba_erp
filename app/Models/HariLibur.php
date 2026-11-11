<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class HariLibur extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'tanggal', 'nama', 'tipe', 'cabang_id',
    ];

    protected $fillable = ['tanggal', 'nama', 'tipe', 'cabang_id'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    /**
     * Cek apakah tanggal tertentu adalah hari libur (nasional atau khusus cabang).
     */
    public static function isLibur(string|Carbon $tanggal, ?int $cabangId = null): bool
    {
        $tgl = $tanggal instanceof Carbon ? $tanggal->format('Y-m-d') : $tanggal;

        return static::query()
            ->where('tanggal', $tgl)
            ->where(function ($q) use ($cabangId) {
                $q->where('tipe', 'nasional')
                  ->orWhere(function ($q2) use ($cabangId) {
                      $q2->where('tipe', 'cabang')
                         ->where('cabang_id', $cabangId);
                  });
            })
            ->exists();
    }

    /**
     * Ambil tanggal libur dalam rentang tertentu (set string 'Y-m-d').
     */
    public static function getTanggalLibur(
        string $dari,
        string $sampai,
        ?int $cabangId = null
    ): array {
        return static::query()
            ->whereBetween('tanggal', [$dari, $sampai])
            ->where(function ($q) use ($cabangId) {
                $q->where('tipe', 'nasional')
                  ->orWhere(function ($q2) use ($cabangId) {
                      $q2->where('tipe', 'cabang')
                         ->where('cabang_id', $cabangId);
                  });
            })
            ->pluck('tanggal')
            ->map(fn($t) => $t instanceof Carbon ? $t->format('Y-m-d') : (string) $t)
            ->flip()
            ->all();
    }
}
