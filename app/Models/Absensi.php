<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Absensi extends Model
{
    use HasFactory, HasCabang, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'karyawan_id', 'tanggal', 'cabang_id', 'status', 'tipe_absensi',
        'jam_masuk', 'jam_keluar', 'jam_lembur_masuk', 'jam_lembur_keluar',
        'is_telat', 'menit_telat', 'keterangan', 'dicatat_oleh',
    ];

    protected $fillable = [
        'karyawan_id',
        'cabang_id',
        'tanggal',
        'status',
        'is_telat',
        'menit_telat',
        'jam_masuk',
        'tipe_absensi',
        'jam_keluar',
        'jam_lembur',
        'jam_lembur_masuk',
        'jam_lembur_keluar',
        'keterangan',
        'dicatat_oleh',
        // GPS per tipe
        'lat_masuk', 'lng_masuk',
        'lat_keluar', 'lng_keluar',
        'lat_lembur_masuk', 'lng_lembur_masuk',
        'lat_lembur_keluar', 'lng_lembur_keluar',
        // Foto per tipe
        'foto_masuk', 'foto_keluar', 'foto_lembur_masuk', 'foto_lembur_keluar',
        // FK ke face_attendances per tipe
        'face_attendance_masuk_id',
        'face_attendance_keluar_id',
        'face_attendance_lembur_masuk_id',
        'face_attendance_lembur_keluar_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'           => 'date',
            'is_telat'          => 'boolean',
            'menit_telat'       => 'integer',
            'jam_masuk'         => 'datetime:H:i',
            'jam_keluar'        => 'datetime:H:i',
            'jam_lembur_masuk'  => 'datetime:H:i',
            'jam_lembur_keluar' => 'datetime:H:i',
            'jam_lembur'        => 'decimal:2',
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

    public function dicatatOleh()
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function faceAttendanceMasuk()
    {
        return $this->belongsTo(FaceAttendance::class, 'face_attendance_masuk_id');
    }

    public function faceAttendanceKeluar()
    {
        return $this->belongsTo(FaceAttendance::class, 'face_attendance_keluar_id');
    }

    public function faceAttendanceLemburMasuk()
    {
        return $this->belongsTo(FaceAttendance::class, 'face_attendance_lembur_masuk_id');
    }

    public function faceAttendanceLemburKeluar()
    {
        return $this->belongsTo(FaceAttendance::class, 'face_attendance_lembur_keluar_id');
    }
}
