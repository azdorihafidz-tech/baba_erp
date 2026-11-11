<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Riwayat status pesanan online (siapa, kapan, catatan). */
class PesananOnlineLog extends Model
{
    protected $table = 'pesanan_online_log';

    public const UPDATED_AT = null;

    protected $fillable = ['pesanan_online_id', 'status', 'catatan', 'oleh_jenis', 'oleh_id', 'oleh_nama'];
}
