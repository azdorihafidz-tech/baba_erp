<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiBatch extends Model
{
    protected $fillable = [
        'kode_batch', 'tanggal', 'cabang_id', 'item_id', 'resep_produksi_id',
        'qty_rencana', 'qty_hasil', 'biaya_bahan', 'modal_otomatis', 'modal_per_unit', 'modal_manual',
        'status', 'catatan', 'user_id', 'dibatalkan_by', 'dibatalkan_at', 'alasan_batal',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date',
            'qty_rencana'    => 'decimal:3',
            'qty_hasil'      => 'decimal:3',
            'biaya_bahan'    => 'decimal:2',
            'modal_otomatis' => 'decimal:2',
            'modal_per_unit' => 'decimal:2',
            'modal_manual'   => 'boolean',
            'dibatalkan_at'  => 'datetime',
        ];
    }

    public function cabang()       { return $this->belongsTo(Cabang::class); }
    public function item()         { return $this->belongsTo(Item::class); }
    public function resep()        { return $this->belongsTo(ResepProduksi::class, 'resep_produksi_id'); }
    public function bahan()        { return $this->hasMany(ProduksiBatchItem::class); }
    public function user()         { return $this->belongsTo(User::class); }
    public function dibatalkanOleh() { return $this->belongsTo(User::class, 'dibatalkan_by'); }

    /** Nilai stok hasil = qty hasil × modal per unit yang dipakai. */
    public function getNilaiHasilAttribute(): float
    {
        return round((float) $this->qty_hasil * (float) $this->modal_per_unit, 2);
    }
}
