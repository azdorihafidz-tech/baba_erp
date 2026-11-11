<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResepBumbu extends Model
{
    protected $table = 'resep_bumbu';

    protected $fillable = [
        'cabang_id',
        'nama',
        'kode',
        'jenis_olahan_id',
        // Tahap 3 D'mentai — resep otomatis ketemu saat kasir klik produk di POS
        'item_id',
        'is_active',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items()
    {
        return $this->hasMany(ResepBumbuItem::class)->orderBy('urutan');
    }

    public function jenisOlahan()
    {
        return $this->belongsTo(JenisOlahan::class, 'jenis_olahan_id');
    }

    /** Pemilik cabang resep / Bumbu Pusat (CLAUDE.md 4.39). */
    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function scopeMilikCabang($query, ?int $cabangId)
    {
        // cabang_id NULL = data lama belum dipecah → tampil di semua cabang (pengaman)
        return $cabangId
            ? $query->where(fn ($q) => $q->where($this->getTable() . '.cabang_id', $cabangId)->orWhereNull($this->getTable() . '.cabang_id'))
            : $query;
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }
}
