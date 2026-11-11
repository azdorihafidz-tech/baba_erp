<?php

namespace App\Models;

use App\Enums\TipeStockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMovement extends Model
{
    use HasFactory;

    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->created_at ??= now());
    }

    protected $fillable = [
        'item_id',
        'lokasi_asal_id',
        'lokasi_tujuan_id',
        'qty',
        'tipe',
        'referensi_type',
        'referensi_id',
        'catatan',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => TipeStockMovement::class,
            'qty' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function lokasiAsal()
    {
        return $this->belongsTo(Cabang::class, 'lokasi_asal_id');
    }

    public function lokasiTujuan()
    {
        return $this->belongsTo(Cabang::class, 'lokasi_tujuan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function referensi()
    {
        return $this->morphTo('referensi');
    }
}
