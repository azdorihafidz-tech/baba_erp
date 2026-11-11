<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Stok parfum milik seorang member (ERP BABA Tahap 2c). Perubahan hanya lewat StokMemberService. */
class StokMember extends Model
{
    protected $table = 'stok_member';

    protected $fillable = ['member_id', 'item_id', 'qty', 'nilai_rata'];

    protected function casts(): array
    {
        return ['member_id' => 'integer', 'item_id' => 'integer', 'qty' => 'integer', 'nilai_rata' => 'decimal:2'];
    }

    public function item()   { return $this->belongsTo(Item::class); }
    public function member() { return $this->belongsTo(MemberAkun::class, 'member_id', 'member_id'); }
}
