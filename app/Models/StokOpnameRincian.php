<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pecahan selisih satu barang per penyebab (+ foto bukti). Pencatatan keuangan per penyebab di StokOpnameService::PENYEBAB. */
class StokOpnameRincian extends Model
{
    protected $table = 'stok_opname_rincian';

    protected $fillable = ['stok_opname_item_id', 'penyebab', 'qty', 'nilai', 'foto', 'catatan'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'nilai' => 'float'];
    }

    public function itemOpname() { return $this->belongsTo(StokOpnameItem::class, 'stok_opname_item_id'); }

    public function getPenyebabLabelAttribute(): string
    {
        return \App\Services\StokOpnameService::PENYEBAB[$this->penyebab]['label'] ?? $this->penyebab;
    }
}
