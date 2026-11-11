<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Karyawan extends Model
{
    use HasFactory, HasCabang, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'nama_lengkap', 'nik', 'jabatan', 'tipe_karyawan',
        'cabang_id', 'shift_id', 'atasan_id',
        'gaji_pokok', 'tunjangan_jabatan', 'tunjangan_makan', 'tunjangan_transport',
        'tunjangan_bpjs_kesehatan_persen', 'tunjangan_bpjs_tk_persen',
        'status', 'tanggal_masuk', 'tanggal_keluar',
    ];

    protected $fillable = [
        'cabang_id',
        'shift_id',
        'user_id',
        'nik',
        'nama_lengkap',
        'jenis_kelamin',
        'tanggal_lahir',
        'alamat',
        'telepon',
        'jabatan',
        'tipe_karyawan',
        'tanggal_masuk',
        'tanggal_keluar',
        'gaji_pokok',
        'no_rekening',
        'nama_bank',
        'status',
        'atasan_id',
        'foto',
        'catatan',
        'tunjangan_jabatan',
        'tunjangan_makan',
        'tunjangan_transport',
        'tunjangan_bpjs_kesehatan_persen',
        'tunjangan_bpjs_tk_persen',
        'face_data',
        'face_photos',
        'face_registered_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'tanggal_masuk' => 'date',
            'tanggal_keluar' => 'date',
            'gaji_pokok'                      => 'decimal:2',
            'tunjangan_jabatan'               => 'decimal:2',
            'tunjangan_makan'                 => 'decimal:2',
            'tunjangan_transport'             => 'decimal:2',
            'tunjangan_bpjs_kesehatan_persen' => 'decimal:2',
            'tunjangan_bpjs_tk_persen'        => 'decimal:2',
            'face_photos'                     => 'array',
            'face_registered_at'              => 'datetime',
        ];
    }

    public function faceAttendances()
    {
        return $this->hasMany(FaceAttendance::class);
    }

    public function isFaceRegistered(): bool
    {
        return !empty($this->face_data) && $this->face_registered_at !== null;
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function atasan()
    {
        return $this->belongsTo(Karyawan::class, 'atasan_id');
    }

    public function bawahan()
    {
        return $this->hasMany(Karyawan::class, 'atasan_id');
    }

    public function absensis()
    {
        return $this->hasMany(Absensi::class);
    }

    public function penggajians()
    {
        return $this->hasMany(Penggajian::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    /**
     * Rekap absensi untuk satu periode bulan.
     * Menghitung hari hadir, lembur, telat, alpha, dan total jam dari tabel absensis.
     */
    public function rekapAbsensi(int $bulan, int $tahun): array
    {
        $start = \Carbon\Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        // Hari kerja menggunakan hari_kerja_per_minggu & hari libur
        $hariKerjaPekan = (int) (\App\Models\PengaturanGaji::getSetting('hari_kerja_per_minggu', 6) ?? 6);
        $hariLiburSet   = \App\Models\HariLibur::getTanggalLibur(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            $this->cabang_id
        );
        $hariKerja = 0;
        $d = $start->copy();
        while ($d->lte($end)) {
            $dow    = $d->dayOfWeek;
            $libur  = isset($hariLiburSet[$d->format('Y-m-d')]);
            $mingguan = ($dow === 0) || ($hariKerjaPekan <= 5 && $dow === 6);
            if (!$mingguan && !$libur) $hariKerja++;
            $d->addDay();
        }

        $absensis = $this->absensis()
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->get();

        $hariHadir  = $absensis->whereNotNull('jam_masuk')->count();
        $hariLembur = $absensis->whereNotNull('jam_lembur_masuk')->count();
        $hariTelat  = $absensis->where('is_telat', true)->count();
        $hariIzin   = $absensis->where('status', 'izin')->count();
        $hariSakit  = $absensis->where('status', 'sakit')->count();
        $hariLibur  = $absensis->whereIn('status', ['libur', 'cuti'])->count();
        $hariAlpha  = max(0, $hariKerja - $hariHadir - $hariIzin - $hariSakit - $hariLibur);

        // Hitung total jam kerja dan jam lembur dari kolom waktu masuk/keluar
        $totalMenitKerja  = 0;
        $totalMenitLembur = 0;
        foreach ($absensis as $abs) {
            if ($abs->jam_masuk && $abs->jam_keluar) {
                try {
                    $m = \Carbon\Carbon::createFromFormat('H:i:s', substr($abs->getRawOriginal('jam_masuk'), 0, 8));
                    $k = \Carbon\Carbon::createFromFormat('H:i:s', substr($abs->getRawOriginal('jam_keluar'), 0, 8));
                    if ($k->gt($m)) $totalMenitKerja += $m->diffInMinutes($k);
                } catch (\Exception $e) {}
            }
            if ($abs->jam_lembur_masuk && $abs->jam_lembur_keluar) {
                try {
                    $lm = \Carbon\Carbon::createFromFormat('H:i:s', substr($abs->getRawOriginal('jam_lembur_masuk'), 0, 8));
                    $lk = \Carbon\Carbon::createFromFormat('H:i:s', substr($abs->getRawOriginal('jam_lembur_keluar'), 0, 8));
                    if ($lk->gt($lm)) $totalMenitLembur += $lm->diffInMinutes($lk);
                } catch (\Exception $e) {}
            }
        }

        // Fallback ke kolom jam_lembur (float) jika tidak ada data masuk/keluar lembur
        $jamLemburTotal = $totalMenitLembur > 0
            ? round($totalMenitLembur / 60, 2)
            : (float) $absensis->sum('jam_lembur');

        return [
            'hari_kerja'       => $hariKerja,
            'hari_hadir'       => $hariHadir,
            'hari_alpha'       => $hariAlpha,
            'hari_izin'        => $hariIzin,
            'hari_sakit'       => $hariSakit,
            'hari_lembur'      => $hariLembur,
            'hari_telat'       => $hariTelat,
            'total_jam_kerja'  => round($totalMenitKerja / 60, 2),
            'total_jam_lembur' => $jamLemburTotal,
        ];
    }
}
