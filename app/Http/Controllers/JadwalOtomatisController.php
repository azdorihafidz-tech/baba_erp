<?php

namespace App\Http\Controllers;

use App\Services\JadwalOtomatisService;
use Illuminate\Http\Request;

/** ERP BABA Tahap 8 — Pengaturan › Jadwal Otomatis (Owner, route grup role:owner). */
class JadwalOtomatisController extends Controller
{
    public function __construct(private JadwalOtomatisService $svc)
    {
    }

    public function index()
    {
        return view('pengaturan.jadwal-otomatis', [
            'tugas' => $this->svc->semua(), 'cronTerakhir' => $this->svc->cronTerakhir(), 'cronHidup' => $this->svc->cronHidup(),
            'cadangan' => $this->svc->cadangan(), 'cadanganSehat' => $this->svc->cadanganSehat(), 'baris' => $this->svc->barisCron(),
            'hariPengingat' => JadwalOtomatisService::hariPengingat(),
        ]);
    }

    public function simpan(Request $request)
    {
        try {
            $ubah = $this->svc->simpan($request->only(['aktif', 'jam', 'hari_sebelum']));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', e($e->getMessage()))->withInput();
        }
        if ($ubah) {
            activity('JadwalOtomatis')->causedBy(auth()->user())->withProperties(['perubahan' => $ubah])->log('Jadwal otomatis diubah: ' . implode('; ', $ubah));
        }

        return back()->with('success', e($ubah ? 'Jadwal otomatis tersimpan: ' . implode('; ', $ubah) . '.' : 'Tidak ada perubahan.'));
    }

    public function jalankan(string $kode)
    {
        abort_unless(array_key_exists($kode, JadwalOtomatisService::TUGAS), 404);
        $h = $this->svc->jalankan($kode, 'manual');
        $label = JadwalOtomatisService::TUGAS[$kode]['label'];
        activity('JadwalOtomatis')->causedBy(auth()->user())->log("Jalankan sekarang: {$label} ({$h['status']})");

        return back()->with($h['status'] === 'berhasil' ? 'success' : 'error', e("{$label}: " . ($h['status'] === 'berhasil' ? 'selesai' : 'gagal') . ' — ' . mb_substr($h['pesan'] ?: '-', 0, 200)));
    }

    /** Unduh salah satu file cadangan database (hanya nama file berpola erp-*.sql.gz di folder cadangan). */
    public function unduhCadangan(string $nama)
    {
        abort_unless(preg_match('/^erp-\d{8}-\d{4}\.sql\.gz$/', $nama) === 1, 404);
        $path = $this->svc->folderCadangan() . DIRECTORY_SEPARATOR . $nama;
        abort_unless(is_file($path), 404);
        activity('JadwalOtomatis')->causedBy(auth()->user())->log("Unduh cadangan database {$nama}");

        return response()->download($path, $nama, ['Content-Type' => 'application/gzip']);
    }
}
