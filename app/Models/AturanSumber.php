<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

/** Boleh belanja ke gudang / ke upline: untuk semua / satu jabatan / satu akun. Tanpa aturan = gudang saja. */
class AturanSumber extends Model
{
    use HasAuditLog;

    protected $table = 'toko_online_aturan_sumber';

    protected $fillable = ['cakupan', 'jabatan_id', 'member_id', 'boleh_gudang', 'boleh_upline', 'aktif', 'catatan', 'created_by'];

    protected function casts(): array
    {
        return ['jabatan_id' => 'integer', 'member_id' => 'integer', 'boleh_gudang' => 'boolean', 'boleh_upline' => 'boolean', 'aktif' => 'boolean'];
    }

    public function akun() { return $this->belongsTo(MemberAkun::class, 'member_id', 'member_id'); }
}
