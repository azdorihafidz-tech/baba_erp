<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Stok opname member (ERP BABA Tahap 7f). Aturan di OpnameMemberService. */
class OpnameMember extends Model
{
    protected $table = 'opname_member';

    protected $fillable = ['nomor', 'member_id', 'total_nilai', 'catatan', 'foto'];

    protected function casts(): array
    {
        return ['member_id' => 'integer', 'total_nilai' => 'float'];
    }

    public function item()   { return $this->hasMany(OpnameMemberItem::class)->orderBy('id'); }
    public function member() { return $this->belongsTo(MemberAkun::class, 'member_id', 'member_id'); }
}
