<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class BackupController extends Controller
{
    private string $disk = 'local';
    private string $folder = 'backups';

    public function index()
    {
        abort_unless(auth()->user()->can('lihat_backup'), 403, 'Anda tidak memiliki akses ke Backup Database.');

        $files = $this->getBackupFiles();
        return view('backup.index', compact('files'));
    }

    public function run()
    {
        abort_unless(auth()->user()->can('buat_backup'), 403, 'Anda tidak memiliki akses untuk membuat backup.');

        try {
            Artisan::call('backup:run', ['--only-db' => true, '--disable-notifications' => true]);
            $output = Artisan::output();
            return back()->with('success', 'Backup berhasil dibuat. ' . trim($output));
        } catch (\Exception $e) {
            return back()->with('error', 'Backup gagal: ' . $e->getMessage());
        }
    }

    public function download(string $filename)
    {
        abort_unless(auth()->user()->can('download_backup'), 403, 'Anda tidak memiliki akses untuk download backup.');

        $path = $this->folder . '/' . $filename;
        if (!Storage::disk($this->disk)->exists($path)) {
            abort(404, 'File backup tidak ditemukan.');
        }
        return Storage::disk($this->disk)->download($path);
    }

    public function destroy(string $filename)
    {
        abort_unless(auth()->user()->can('hapus_backup'), 403, 'Anda tidak memiliki akses untuk menghapus backup.');

        $path = $this->folder . '/' . $filename;
        if (Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->delete($path);
        }
        return back()->with('success', "Backup {$filename} berhasil dihapus.");
    }

    private function getBackupFiles(): array
    {
        $files = [];

        // Cari semua file zip di semua subfolder backup
        $allFiles = Storage::disk($this->disk)->allFiles($this->folder);

        foreach ($allFiles as $filePath) {
            if (!str_ends_with($filePath, '.zip')) {
                continue;
            }
            $size    = Storage::disk($this->disk)->size($filePath);
            $lastMod = Storage::disk($this->disk)->lastModified($filePath);

            $files[] = [
                'path'      => $filePath,
                'name'      => basename($filePath),
                'folder'    => dirname($filePath),
                'size'      => $this->formatSize($size),
                'size_raw'  => $size,
                'date'      => \Carbon\Carbon::createFromTimestamp($lastMod)->format('d/m/Y H:i'),
                'timestamp' => $lastMod,
            ];
        }

        usort($files, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

        return $files;
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 2)    . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 2)       . ' KB';
        return $bytes . ' B';
    }
}
