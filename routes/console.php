<?php

use App\Services\JadwalOtomatisService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * ERP BABA Tahap 8 — Jadwal Otomatis.
 * Hosting mematikan proc_open: Schedule::command() membuka proses baru dan gagal diam-diam. Semua tugas dijalankan
 * DI DALAM proses (Schedule::call → JadwalOtomatisService::jalankan → Artisan::call). Hidup/mati & jam diatur Owner
 * di Pengaturan › Jadwal Otomatis (tabel jadwal_otomatis); cron cPanel cukup `php artisan schedule:run` tiap menit.
 * Cadangan database TIDAK di sini (mysqldump butuh proses luar) → baris cron cPanel kedua, lihat JadwalOtomatisService::barisCron().
 */
$jadwal = JadwalOtomatisService::pengaturanJadwal();
$tugas = fn (string $kode) => Schedule::call(fn () => app(JadwalOtomatisService::class)->jalankan($kode, 'jadwal'))
    ->name("jadwal:{$kode}")
    ->withoutOverlapping(60)
    ->when(fn () => app(JadwalOtomatisService::class)->aktif($kode));

// detak cron (pita merah untuk Owner bila cron berhenti)
Schedule::call(fn () => app(JadwalOtomatisService::class)->detak())->name('jadwal:detak')->everyMinute();

$tugas('ro_kirim')->everyMinute();
$tugas('pesanan_online')->hourly();
$tugas('recurring')->dailyAt($jadwal['recurring']['jam'] ?? '00:01');
$tugas('stok_opname_pengingat')->dailyAt($jadwal['stok_opname_pengingat']['jam'] ?? '08:00');
$tugas('aset_depresiasi')->monthlyOn(1, $jadwal['aset_depresiasi']['jam'] ?? '01:00');
$tugas('loyalty')->dailyAt($jadwal['loyalty']['jam'] ?? '03:00');
