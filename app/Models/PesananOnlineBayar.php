<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bukti transfer pesanan online. File di storage/app/bukti-pesanan (tidak publik), dilihat lewat rute ber-izin. */
class PesananOnlineBayar extends Model
{
    protected $table = 'pesanan_online_bayar';

    public const STATUS = ['menunggu' => 'Menunggu dicek', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];

    protected $fillable = ['pesanan_online_id', 'file', 'hash', 'nominal', 'status', 'alasan', 'dicek_by', 'dicek_at'];

    protected function casts(): array
    {
        return ['nominal' => 'decimal:2', 'dicek_at' => 'datetime'];
    }

    public function pesanan()  { return $this->belongsTo(PesananOnline::class, 'pesanan_online_id'); }
    public function pengecek() { return $this->belongsTo(User::class, 'dicek_by'); }
}
