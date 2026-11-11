<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catatan RO dari belanja online yang dikirim ke aplikasi member (ERP BABA Tahap 2e). Lihat RoService. */
class RoCatatan extends Model
{
    protected $table = 'ro_catatan';

    public const STATUS = ['menunggu' => 'Menunggu kirim', 'terkirim' => 'Terkirim', 'gagal' => 'Gagal (dicoba ulang)'];
    public const JENIS = ['pesanan' => 'Pesanan', 'retur' => 'Retur', 'batal' => 'Pesanan batal'];

    protected $fillable = ['ref', 'member_id', 'qty', 'jenis', 'pesanan_online_id', 'retur_online_id', 'tanggal', 'mode', 'perlu_approve',
        'status', 'percobaan', 'pesan_galat', 'member_doc_id', 'terkirim_at'];

    protected function casts(): array
    {
        return ['member_id' => 'integer', 'qty' => 'integer', 'tanggal' => 'date', 'perlu_approve' => 'boolean', 'percobaan' => 'integer',
            'member_doc_id' => 'integer', 'terkirim_at' => 'datetime'];
    }

    public function pesanan() { return $this->belongsTo(PesananOnline::class, 'pesanan_online_id'); }
    public function retur()   { return $this->belongsTo(ReturOnline::class, 'retur_online_id'); }
    public function member()  { return $this->belongsTo(MemberAkun::class, 'member_id', 'member_id'); }
}
