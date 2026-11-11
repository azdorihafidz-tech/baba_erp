<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiBatch extends Model
{
    protected $fillable = [
        'kode_batch', 'tanggal', 'tanggal_siap', 'cabang_id', 'item_id', 'resep_produksi_id',
        'qty_rencana', 'qty_hasil', 'biaya_bahan', 'modal_otomatis', 'modal_per_unit', 'modal_manual',
        'status', 'catatan', 'user_id', 'dibatalkan_by', 'dibatalkan_at', 'alasan_batal',
        'diselesaikan_by', 'diselesaikan_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date',
            'tanggal_siap'   => 'date',
            'diselesaikan_at' => 'datetime',
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
    public function diselesaikanOleh() { return $this->belongsTo(User::class, 'diselesaikan_by'); }

    /** BABA: batch diperam — bahan sudah terpotong, hasil belum masuk stok. */
    public function getSedangDiperamAttribute(): bool
    {
        return $this->status === 'diperam';
    }

    /** Sudah boleh diselesaikan (hari ini ≥ tanggal siap). */
    public function getSiapDiselesaikanAttribute(): bool
    {
        return $this->status === 'diperam' && (! $this->tanggal_siap || ! $this->tanggal_siap->isFuture());
    }

    /** Susut = rencana − hasil akhir (hanya untuk batch selesai). */
    public function getSusutAttribute(): float
    {
        return $this->status === 'selesai' ? max(0, round((float) $this->qty_rencana - (float) $this->qty_hasil, 3)) : 0.0;
    }

    /** Nilai stok hasil = qty hasil × modal per unit yang dipakai. */
    public function getNilaiHasilAttribute(): float
    {
        return round((float) $this->qty_hasil * (float) $this->modal_per_unit, 2);
    }
}
