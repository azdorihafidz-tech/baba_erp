<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Tindak lanjut stok rusak: pakai lagi / musnahkan / kembali ke pemasok (diganti barang, uang kembali, potong tagihan). */
class StokRusakTindakan extends Model
{
    protected $table = 'stok_rusak_tindakan';

    public $timestamps = false;

    public const JENIS = [
        'pakai_lagi'     => 'Dipakai lagi (kembali ke stok jual)',
        'pemasok_ganti'  => 'Kembali ke pemasok — diganti barang baru',
        'pemasok_uang'   => 'Kembali ke pemasok — uang dikembalikan',
        'pemasok_potong' => 'Kembali ke pemasok — dipotong dari tagihan',
        'musnahkan'      => 'Dimusnahkan',
    ];

    protected $fillable = ['stok_rusak_id', 'jenis', 'qty', 'nilai_pokok', 'nilai_pulih', 'supplier_id', 'kas_id', 'transaksi_keuangan_id', 'foto', 'catatan', 'user_id', 'created_at'];

    protected function casts(): array
    {
        return ['qty' => 'float', 'nilai_pokok' => 'float', 'nilai_pulih' => 'float', 'created_at' => 'datetime'];
    }

    public function stokRusak() { return $this->belongsTo(StokRusak::class); }
    public function supplier()  { return $this->belongsTo(Supplier::class); }
    public function kas()       { return $this->belongsTo(Kas::class); }
    public function user()      { return $this->belongsTo(User::class); }

    public function getJenisLabelAttribute(): string { return self::JENIS[$this->jenis] ?? $this->jenis; }
}
