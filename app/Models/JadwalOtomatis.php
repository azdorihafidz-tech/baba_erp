<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Saklar & catatan tugas terjadwal (ERP BABA Tahap 8). Aturan di JadwalOtomatisService. */
class JadwalOtomatis extends Model
{
    protected $table = 'jadwal_otomatis';

    protected $fillable = ['kode', 'aktif', 'jam', 'opsi', 'terakhir_mulai', 'terakhir_selesai', 'terakhir_status', 'terakhir_pesan', 'terakhir_oleh'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'opsi' => 'array', 'terakhir_mulai' => 'datetime', 'terakhir_selesai' => 'datetime'];
    }
}
