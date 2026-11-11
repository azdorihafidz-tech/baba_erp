<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permission extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'name',
        'display_name',
        'group',
        'description',
    ];
}
