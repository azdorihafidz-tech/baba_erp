<?php

namespace App\Http\Controllers;

use App\Services\JadwalOtomatisService;

/**
 * Keamanan → Backup Database. Porting Dimsum 4.50 audit + keputusan Owner P6 (18 Nov 2026):
 * dulu memakai `backup:run` (spatie) yang gagal di hosting (proc_open mati). Cadangan sungguhan kini dibuat
 * cron cPanel tiap hari (lihat Pengaturan › Jadwal Otomatis) — halaman ini menampilkan & mengunduh file itu.
 */
class BackupController extends Controller
{
    private const POLA = '/^erp-\d{8}-\d{4}\.sql\.gz$/';

    public function __construct(private JadwalOtomatisService $jadwal)
    {
    }

    public function index()
    {
        abort_unless(auth()->user()->can('lihat_backup'), 403, 'Anda tidak memiliki akses ke Backup Database.');

        return view('backup.index', [
            'files' => $this->jadwal->cadangan(),
            'sehat' => $this->jadwal->cadanganSehat(),
            'simpanHari' => JadwalOtomatisService::SIMPAN_CADANGAN_HARI,
        ]);
    }

    /** Tombol lama "Buat backup" — tidak dipakai lagi (gagal di hosting). */
    public function run()
    {
        abort_unless(auth()->user()->can('buat_backup'), 403, 'Anda tidak memiliki akses untuk membuat backup.');

        return back()->with('warning', 'Cadangan database dibuat otomatis setiap hari jam 02.00 oleh server. Cara memasangnya ada di Pengaturan › Jadwal Otomatis.');
    }

    public function download(string $filename)
    {
        abort_unless(auth()->user()->can('download_backup'), 403, 'Anda tidak memiliki akses untuk download backup.');
        $path = $this->path($filename);

        return response()->download($path, $filename, ['Content-Type' => 'application/gzip']);
    }

    public function destroy(string $filename)
    {
        abort_unless(auth()->user()->can('hapus_backup'), 403, 'Anda tidak memiliki akses untuk menghapus backup.');
        @unlink($this->path($filename));

        return back()->with('success', "Cadangan {$filename} dihapus.");
    }

    private function path(string $filename): string
    {
        abort_unless(preg_match(self::POLA, $filename) === 1, 404, 'File cadangan tidak ditemukan.');
        $path = $this->jadwal->folderCadangan() . DIRECTORY_SEPARATOR . $filename;
        abort_unless(is_file($path), 404, 'File cadangan tidak ditemukan.');

        return $path;
    }
}
