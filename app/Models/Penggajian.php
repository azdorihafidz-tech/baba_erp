<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penggajian extends Model
{
    use HasFactory, HasCabang, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'karyawan_id', 'cabang_id', 'periode',
        'gaji_pokok', 'tunjangan', 'tunjangan_jabatan', 'tunjangan_makan',
        'tunjangan_transport', 'tunjangan_kehadiran',
        'uang_lembur', 'bonus', 'insentif', 'thr', 'komisi',
        'potongan_absensi', 'potongan_lain',
        'bpjs_kesehatan', 'bpjs_ketenagakerjaan', 'pph21', 'kasbon',
        'total_gaji', 'status', 'tanggal_bayar',
        'tarif_lembur_per_jam_override', 'potongan_alpa_per_hari_override',
        'uang_lembur_manual', 'potongan_alpa_manual',
    ];

    protected $fillable = [
        'karyawan_id',
        'cabang_id',
        'periode',
        'jumlah_hari_kerja',
        'jumlah_hadir',
        'jumlah_alpha',
        'jam_lembur_total',
        'gaji_pokok',
        'tunjangan',
        'uang_lembur',
        'bonus',
        'potongan_absensi',
        'potongan_lain',
        'bpjs_kesehatan',
        'bpjs_ketenagakerjaan',
        'pph21',
        'kasbon',
        'total_gaji',
        'status',
        'tanggal_bayar',
        'catatan',
        'approved_by',
        'tunjangan_jabatan',
        'tunjangan_makan',
        'tunjangan_transport',
        'tunjangan_kehadiran',
        'insentif',
        'thr',
        'komisi',
        'tarif_lembur_per_jam_override',
        'potongan_alpa_per_hari_override',
        'uang_lembur_manual',
        'potongan_alpa_manual',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_bayar' => 'date',
            'jam_lembur_total' => 'decimal:2',
            'gaji_pokok'           => 'decimal:2',
            'tunjangan'            => 'decimal:2',
            'tunjangan_jabatan'    => 'decimal:2',
            'tunjangan_makan'      => 'decimal:2',
            'tunjangan_transport'  => 'decimal:2',
            'tunjangan_kehadiran'  => 'decimal:2',
            'uang_lembur'          => 'decimal:2',
            'bonus'                => 'decimal:2',
            'insentif'             => 'decimal:2',
            'thr'                  => 'decimal:2',
            'komisi'               => 'decimal:2',
            'potongan_absensi'     => 'decimal:2',
            'bpjs_kesehatan'       => 'decimal:2',
            'bpjs_ketenagakerjaan' => 'decimal:2',
            'pph21'                => 'decimal:2',
            'kasbon'               => 'decimal:2',
            'potongan_lain'        => 'decimal:2',
            'total_gaji'                     => 'decimal:2',
            'tarif_lembur_per_jam_override'  => 'decimal:2',
            'potongan_alpa_per_hari_override' => 'decimal:2',
            'uang_lembur_manual'             => 'boolean',
            'potongan_alpa_manual'           => 'boolean',
        ];
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
