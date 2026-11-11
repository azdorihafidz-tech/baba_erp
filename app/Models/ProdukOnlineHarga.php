<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

/** Harga produk online: tetap / gudang / per jabatan (jabatan_id) / khusus akun (member_id). Perubahan tercatat (audit log). */
class ProdukOnlineHarga extends Model
{
    use HasAuditLog;

    protected $table = 'produk_online_harga';

    protected $fillable = ['produk_online_id', 'jenis', 'jabatan_id', 'member_id', 'harga'];

    protected function casts(): array
    {
        return ['harga' => 'decimal:2', 'jabatan_id' => 'integer', 'member_id' => 'integer'];
    }

    public function produk() { return $this->belongsTo(ProdukOnline::class, 'produk_online_id'); }
}
