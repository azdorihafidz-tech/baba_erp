<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequestHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_request_id',
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
