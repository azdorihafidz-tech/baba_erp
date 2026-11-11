<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

/** Izin bayar dengan tagihan (tempo): untuk semua / satu jabatan / satu akun, dengan batas nominal & lama tempo. */
class AturanTagihan extends Model
{
    use HasAuditLog;

    protected $table = 'toko_online_aturan_tagihan';

    public const CAKUPAN = ['semua' => 'Semua member', 'jabatan' => 'Satu jabatan', 'akun' => 'Satu akun'];

    protected $fillable = ['cakupan', 'jabatan_id', 'member_id', 'batas_nominal', 'tempo_hari', 'aktif', 'catatan', 'created_by'];

    protected function casts(): array
    {
        return ['jabatan_id' => 'integer', 'member_id' => 'integer', 'batas_nominal' => 'decimal:2', 'tempo_hari' => 'integer', 'aktif' => 'boolean'];
    }

    public function akun() { return $this->belongsTo(MemberAkun::class, 'member_id', 'member_id'); }
}
