<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SetoranApproval extends Model
{
    protected $fillable = ['setoran_id', 'action', 'user_id', 'catatan', 'dilakukan_pada'];

    protected function casts(): array
    {
        return ['dilakukan_pada' => 'datetime'];
    }

    public function setoran()
    {
        return $this->belongsTo(Setoran::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
