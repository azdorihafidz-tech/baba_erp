<?php

namespace App\Models;

use App\Enums\TipePembayaran;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tahap 3 - POS D'mentai. Sumber kebenaran pembagian metode bayar per Order
 * (split payment) — 1 Order bisa punya >1 baris di sini.
 */
class OrderPayment extends Model
{
    use HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'order_id',
        'metode',
        'jumlah',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'metode' => TipePembayaran::class,
            'jumlah' => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
