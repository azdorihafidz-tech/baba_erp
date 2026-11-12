<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpnameMemberItem extends Model
{
    protected $table = 'opname_member_item';

    public $timestamps = false;

    protected $fillable = ['opname_member_id', 'item_id', 'nama', 'satuan', 'stok_sebelum', 'fisik', 'qty', 'penyebab', 'nilai', 'catatan'];

    protected function casts(): array
    {
        return ['stok_sebelum' => 'integer', 'fisik' => 'integer', 'qty' => 'integer', 'nilai' => 'float'];
    }

    public function opname() { return $this->belongsTo(OpnameMember::class, 'opname_member_id'); }

    public function getPenyebabLabelAttribute(): string
    {
        return \App\Services\StokOpnameService::PENYEBAB[$this->penyebab]['label'] ?? $this->penyebab;
    }
}
