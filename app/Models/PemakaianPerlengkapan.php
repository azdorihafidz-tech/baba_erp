<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fase 5 — Modul Perlengkapan (Rule #66). Model BARU, terpisah dari
 * Adjustment Stok existing. Setiap baris = 1 kejadian pemakaian
 * perlengkapan, ditautkan ke 1 StockMovement (tipe=keluar) via
 * referensi_type='pemakaian_perlengkapan' (lihat PemakaianPerlengkapanService).
 */
class PemakaianPerlengkapan extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'item_id',
        'cabang_id',
        'qty',
        'tanggal_pemakaian',
        'nilai',
        'keterangan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'nilai' => 'decimal:2',
            'tanggal_pemakaian' => 'date',
        ];
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
