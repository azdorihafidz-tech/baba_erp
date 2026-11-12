<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pembayaran ganti rugi: tunai (langsung dibayar) atau potong gaji (menunggu sampai slip gaji ditandai dibayar). */
class GantiRugiStokBayar extends Model
{
    protected $table = 'ganti_rugi_stok_bayar';

    protected $fillable = ['ganti_rugi_stok_id', 'jenis', 'jumlah', 'periode', 'kas_id', 'penggajian_id', 'status', 'transaksi_keuangan_id', 'user_id'];

    protected function casts(): array
    {
        return ['jumlah' => 'float'];
    }

    public function gantiRugi()  { return $this->belongsTo(GantiRugiStok::class, 'ganti_rugi_stok_id'); }
    public function kas()        { return $this->belongsTo(Kas::class); }
    public function penggajian() { return $this->belongsTo(Penggajian::class); }
    public function user()       { return $this->belongsTo(User::class); }
}
