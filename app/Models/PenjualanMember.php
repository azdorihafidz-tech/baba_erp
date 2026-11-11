<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Penjualan kasir member ke pelanggan (ERP BABA Tahap 3). Aturan di KasirMemberService. */
class PenjualanMember extends Model
{
    protected $table = 'penjualan_member';

    public const BAYAR = ['tunai' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS'];

    protected $fillable = ['nomor', 'member_id', 'total', 'modal', 'bayar', 'diterima', 'pelanggan_nama', 'pelanggan_hp', 'catatan', 'status', 'batal_alasan', 'batal_at'];

    protected function casts(): array
    {
        return ['member_id' => 'integer', 'total' => 'decimal:2', 'modal' => 'decimal:2', 'diterima' => 'decimal:2', 'batal_at' => 'datetime'];
    }

    public function item()   { return $this->hasMany(PenjualanMemberItem::class); }
    public function member() { return $this->belongsTo(MemberAkun::class, 'member_id', 'member_id'); }
}
