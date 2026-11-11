<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'supplier_id',
        'action',
        'data_lama',
        'changed_by',
        'changed_by_name',
        'changed_at',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'data_lama'  => 'array',
            'changed_at' => 'datetime',
        ];
    }
}
