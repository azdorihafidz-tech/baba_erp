<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Riwayat aksi stok opname (mulai, ajukan, tolak, setujui, batal, minta hitung ulang). */
class StokOpnameLog extends Model
{
    protected $table = 'stok_opname_log';

    public $timestamps = false;

    public const AKSI = ['mulai' => 'Mulai opname', 'ajukan' => 'Diajukan', 'tolak' => 'Ditolak', 'setujui' => 'Disetujui',
        'batal' => 'Dibatalkan', 'hitung_ulang' => 'Minta hitung ulang'];

    protected $fillable = ['stok_opname_id', 'aksi', 'user_id', 'catatan', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function getAksiLabelAttribute(): string { return self::AKSI[$this->aksi] ?? $this->aksi; }
}
