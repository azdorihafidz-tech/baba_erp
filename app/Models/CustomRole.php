<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tahap D 4.38 — role buatan Owner (di luar 7 role sistem RoleUser). */
class CustomRole extends Model
{
    protected $fillable = ['kode', 'nama', 'akses_semua_cabang', 'deskripsi'];

    protected function casts(): array
    {
        return ['akses_semua_cabang' => 'boolean'];
    }
}
