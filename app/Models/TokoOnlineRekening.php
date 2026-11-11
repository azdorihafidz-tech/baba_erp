<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

/** Rekening tujuan transfer pesanan online: per gudang (cabang_id) atau milik upline (member_id, Tahap 2c). */
class TokoOnlineRekening extends Model
{
    use HasAuditLog;

    protected $table = 'toko_online_rekening';

    protected $fillable = ['cabang_id', 'member_id', 'bank', 'nomor', 'atas_nama', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function cabang() { return $this->belongsTo(Cabang::class); }
}
