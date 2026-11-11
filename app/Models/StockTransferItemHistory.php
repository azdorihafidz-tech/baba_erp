<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferItemHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_transfer_item_id',
        'action',
        'data_lama',
        'changed_by',
        'changed_by_name',
        'changed_at',
        'keterangan',
    ];

    protected $casts = [
        'data_lama'  => 'array',
        'changed_at' => 'datetime',
    ];
}
