<?php

namespace App\Notifications;

use App\Models\EvaluationPeriod;
use Illuminate\Notifications\Notification;

class EvaluasiNotification extends Notification
{
    /**
     * @param string $event  dibuka|ditugaskan|reminder|semua_selesai|hasil_final
     */
    public function __construct(
        private EvaluationPeriod $period,
        private string $event,
        private ?float $skorAkhir = null,
        private ?string $predikat = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $nama    = $this->period->nama_periode;
        $cabang  = $this->period->cabang?->nama_cabang ?? '-';
        $deadline = $this->period->deadline_pengisian?->format('d M Y') ?? '-';

        return match ($this->event) {
            'dibuka' => [
                'title'    => 'Periode Penilaian Dibuka',
                'message'  => "Periode penilaian {$nama} telah dibuka. Deadline: {$deadline}.",
                'icon'     => 'bi-clipboard2-check-fill',
                'color'    => 'info',
                'url'      => route('evaluasi.periods'),
                'kategori' => 'evaluasi',
            ],
            'ditugaskan' => [
                'title'    => 'Ditugaskan sebagai Penilai',
                'message'  => "Anda ditugaskan menilai rekan kerja untuk periode {$nama}. Deadline: {$deadline}.",
                'icon'     => 'bi-person-check-fill',
                'color'    => 'info',
                'url'      => route('evaluasi.periods'),
                'kategori' => 'evaluasi',
            ],
            'reminder' => [
                'title'    => 'Reminder: Belum Mengisi Penilaian',
                'message'  => "Anda belum mengisi penilaian {$nama}. Deadline: {$deadline}.",
                'icon'     => 'bi-alarm-fill',
                'color'    => 'warning',
                'url'      => route('evaluasi.periods'),
                'kategori' => 'evaluasi',
            ],
            'semua_selesai' => [
                'title'    => 'Semua Penilaian Lengkap',
                'message'  => "Semua penilaian {$nama} untuk {$cabang} sudah lengkap, siap di-review.",
                'icon'     => 'bi-clipboard2-check-fill',
                'color'    => 'success',
                'url'      => route('evaluasi.periods'),
                'kategori' => 'evaluasi',
            ],
            'hasil_final' => [
                'title'    => 'Hasil Penilaian Tersedia',
                'message'  => "Hasil penilaian {$nama} Anda sudah tersedia." .
                    ($this->skorAkhir ? " Skor: {$this->skorAkhir} ({$this->predikat})" : ''),
                'icon'     => 'bi-award-fill',
                'color'    => 'success',
                'url'      => route('evaluasi.periods'),
                'kategori' => 'evaluasi',
            ],
            default => [
                'title'    => 'Update Penilaian Karyawan',
                'message'  => "Ada pembaruan pada periode penilaian {$nama}.",
                'icon'     => 'bi-clipboard2-fill',
                'color'    => 'info',
                'url'      => route('evaluasi.periods'),
                'kategori' => 'evaluasi',
            ],
        };
    }
}
