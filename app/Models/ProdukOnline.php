<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

/** Barang yang dijual di menu Belanja aplikasi member (ERP BABA Tahap 2a). */
class ProdukOnline extends Model
{
    use HasAuditLog;

    protected $table = 'produk_online';

    protected $fillable = ['item_id', 'nama_tampil', 'kategori', 'deskripsi', 'foto', 'aktif', 'urutan', 'created_by'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'urutan' => 'integer'];
    }

    public function item()  { return $this->belongsTo(Item::class); }
    public function harga() { return $this->hasMany(ProdukOnlineHarga::class); }

    public function hargaJenis(string $jenis): ?float
    {
        $h = $this->harga->first(fn ($x) => $x->jenis === $jenis);

        return $h ? (float) $h->harga : null;
    }

    public function fotoUrl(): ?string
    {
        $path = $this->foto ?: $this->item?->foto;

        return $path ? asset($path) : null;
    }
}
