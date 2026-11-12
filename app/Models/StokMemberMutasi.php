<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Riwayat keluar-masuk stok member. */
class StokMemberMutasi extends Model
{
    protected $table = 'stok_member_mutasi';

    public const UPDATED_AT = null;

    public const JENIS = [
        'beli' => 'Beli (pesanan diterima)', 'jual' => 'Jual ke downline', 'retur_masuk' => 'Retur masuk',
        'retur_keluar' => 'Retur keluar', 'penyesuaian' => 'Penyesuaian', 'opname' => 'Stok opname', 'kasir' => 'Jual di kasir', 'kasir_batal' => 'Batal jual kasir',
    ];

    protected $fillable = ['member_id', 'item_id', 'qty', 'sisa', 'jenis', 'harga', 'referensi_type', 'referensi_id', 'catatan'];

    protected function casts(): array
    {
        return ['qty' => 'integer', 'sisa' => 'integer', 'harga' => 'decimal:2'];
    }

    public function item() { return $this->belongsTo(Item::class); }
}
