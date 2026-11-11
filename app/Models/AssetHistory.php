<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'asset_id',
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
