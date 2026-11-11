<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiBatchItem extends Model
{
    protected $fillable = ['produksi_batch_id', 'item_id', 'qty', 'biaya'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:3', 'biaya' => 'decimal:2'];
    }

    public function batch() { return $this->belongsTo(ProduksiBatch::class, 'produksi_batch_id'); }
    public function item()  { return $this->belongsTo(Item::class); }
}
