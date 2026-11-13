<?php

namespace App\Services;

use App\Models\JadwalOtomatis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ERP BABA Tahap 8 — Jadwal Otomatis (satu-satunya tempat aturannya).
 *
 * Hosting mematikan proc_open, sehingga Schedule::command (membuka proses baru) gagal diam-diam. Semua tugas dijalankan
 * DI DALAM proses lewat Artisan::call dari Schedule::call (routes/console.php). Owner menyalakan/mematikan tugas & mengatur
 * jam yang boleh diubah; Kirim RO selalu hidup. Cron cPanel menjalankan `schedule:run` tiap menit → detak() mencatat waktu
 * (pita merah bila > SELANG_CRON_MENIT). Cadangan database = baris cron cPanel kedua (mysqldump langsung, juga butuh proses
 * luar) → file di storage/app/private/cadangan-db dipantau di sini.
 */
class JadwalOtomatisService
{
    public const TUGAS = [
        'ro_kirim' => ['label' => 'Kirim RO ke aplikasi member', 'perintah' => 'ro:kirim', 'waktu' => 'Tiap menit', 'wajib' => true, 'aktif' => true,
            'jam' => null, 'ubah_jam' => false, 'ket' => 'RO dari belanja online & retur dikirim ke aplikasi member (dicoba ulang bila gagal).'],
        'pesanan_online' => ['label' => 'Pesanan online otomatis', 'perintah' => 'pesanan-online:otomatis', 'waktu' => 'Tiap jam', 'wajib' => false, 'aktif' => false,
            'jam' => null, 'ubah_jam' => false, 'ket' => 'Terima otomatis pesanan yang sudah lama dikirim & batalkan pesanan yang tidak dibayar (batas waktu di Pengaturan Toko Online).'],
        'recurring' => ['label' => 'Transaksi berulang', 'perintah' => 'recurring:generate', 'waktu' => 'Tiap hari', 'wajib' => false, 'aktif' => true,
            'jam' => '00:01', 'ubah_jam' => false, 'ket' => 'Mencatat biaya rutin dari template Keuangan › Transaksi Berulang yang jatuh tempo.'],
        'stok_opname_pengingat' => ['label' => 'Pengingat stok opname akhir bulan', 'perintah' => 'stok-opname:pengingat', 'waktu' => 'Tiap hari', 'wajib' => false, 'aktif' => false,
            'jam' => '08:00', 'ubah_jam' => true, 'opsi' => ['hari_sebelum' => 3], 'ket' => 'Lonceng ke kepala lokasi & Owner untuk lokasi yang belum stok opname menjelang akhir bulan.'],
        'aset_depresiasi' => ['label' => 'Penyusutan aset', 'perintah' => 'aset:generate-depresiasi', 'waktu' => 'Tanggal 1 tiap bulan', 'wajib' => false, 'aktif' => false,
            'jam' => '01:00', 'ubah_jam' => false, 'ket' => 'Mencatat penyusutan bulanan semua aset (garis lurus / saldo menurun).'],
        'loyalty' => ['label' => 'Cek program loyalty', 'perintah' => 'loyalty:cek-pencapaian', 'waktu' => 'Tiap hari', 'wajib' => false, 'aktif' => false,
            'jam' => '03:00', 'ubah_jam' => true, 'ket' => 'Memeriksa pelanggan toko yang baru mencapai target program loyalty.'],
    ];

    public const SELANG_CRON_MENIT = 5;

    public const SIMPAN_CADANGAN_HARI = 14;

    /** Baris jadwal untuk routes/console.php — aman walau database belum ada (migrate pertama). @return array<string, array{aktif:bool, jam:?string, opsi:array}> */
    public static function pengaturanJadwal(): array
    {
        $hasil = [];
        foreach (self::TUGAS as $kode => $t) {
            $hasil[$kode] = ['aktif' => (bool) $t['aktif'], 'jam' => $t['jam'], 'opsi' => $t['opsi'] ?? []];
        }
        try {
            foreach (DB::table('jadwal_otomatis')->get(['kode', 'aktif', 'jam', 'opsi']) as $r) {
                if (isset($hasil[$r->kode])) {
                    $hasil[$r->kode] = ['aktif' => (bool) $r->aktif || self::TUGAS[$r->kode]['wajib'],
                        'jam' => self::TUGAS[$r->kode]['ubah_jam'] && self::jamSah($r->jam) ? $r->jam : self::TUGAS[$r->kode]['jam'],
                        'opsi' => array_merge(self::TUGAS[$r->kode]['opsi'] ?? [], (array) json_decode((string) $r->opsi, true))];
                }
            }
        } catch (\Throwable $e) {
            // tabel belum ada (instal baru) → nilai bawaan
        }

        return $hasil;
    }

    public static function jamSah(?string $jam): bool
    {
        return is_string($jam) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $jam) === 1;
    }

    /** Pastikan setiap tugas punya baris (nilai bawaan keputusan Owner 13 Nov 2026). */
    public function pastikan(): void
    {
        foreach (self::TUGAS as $kode => $t) {
            JadwalOtomatis::firstOrCreate(['kode' => $kode], ['aktif' => $t['aktif'], 'jam' => $t['jam'], 'opsi' => $t['opsi'] ?? null]);
        }
    }

    /** @return Collection<string, JadwalOtomatis> */
    public function semua(): Collection
    {
        $this->pastikan();

        return JadwalOtomatis::whereIn('kode', array_keys(self::TUGAS))->get()->keyBy('kode');
    }

    public function aktif(string $kode): bool
    {
        return (bool) (self::pengaturanJadwal()[$kode]['aktif'] ?? false);
    }

    public static function hariPengingat(): int
    {
        return max(1, min(10, (int) (self::pengaturanJadwal()['stok_opname_pengingat']['opsi']['hari_sebelum'] ?? 3)));
    }

    /** Simpan saklar, jam, dan opsi dari layar. @return array<int, string> perubahan (untuk Log Aktivitas) */
    public function simpan(array $input): array
    {
        $this->pastikan();
        $ubah = [];
        foreach (self::TUGAS as $kode => $t) {
            $row = JadwalOtomatis::where('kode', $kode)->first();
            $baru = ['aktif' => $t['wajib'] ? true : ! empty($input['aktif'][$kode])];
            if ($t['ubah_jam']) {
                $jam = (string) ($input['jam'][$kode] ?? $row->jam);
                if (! self::jamSah($jam)) {
                    throw new \InvalidArgumentException("Jam {$t['label']} tidak valid (format JJ:MM, mis. 08:00).");
                }
                $baru['jam'] = $jam;
            }
            if ($kode === 'stok_opname_pengingat' && isset($input['hari_sebelum'])) {
                $h = (int) $input['hari_sebelum'];
                if ($h < 1 || $h > 10) {
                    throw new \InvalidArgumentException('Pengingat stok opname dimulai 1–10 hari sebelum akhir bulan.');
                }
                $baru['opsi'] = array_merge((array) $row->opsi, ['hari_sebelum' => $h]);
            }
            foreach ($baru as $k => $v) {
                if ($row->{$k} != $v) {
                    $ubah[] = $t['label'] . ': ' . ($k === 'aktif' ? ($v ? 'dihidupkan' : 'dimatikan') : ($k === 'jam' ? "jam {$v}" : 'mulai H-' . $v['hari_sebelum']));
                }
            }
            $row->update($baru);
        }

        return $ubah;
    }

    /** Jalankan satu tugas di dalam proses (tanpa proc_open) dan catat hasilnya. @return array{status:string, pesan:string} */
    public function jalankan(string $kode, string $oleh = 'jadwal'): array
    {
        $t = self::TUGAS[$kode] ?? throw new \InvalidArgumentException('Tugas tidak dikenal.');
        $this->pastikan();
        $row = JadwalOtomatis::where('kode', $kode)->first();
        $row->update(['terakhir_mulai' => now(), 'terakhir_oleh' => $oleh]);
        try {
            $kodeKeluar = Artisan::call($t['perintah']);
            $pesan = trim(preg_replace('/\s+/', ' ', Artisan::output()));
            $status = $kodeKeluar === 0 ? 'berhasil' : 'gagal';
        } catch (\Throwable $e) {
            $status = 'gagal';
            $pesan = $e->getMessage();
            Log::error(__METHOD__ . " {$kode}: " . $e->getMessage());
        }
        $row->update(['terakhir_selesai' => now(), 'terakhir_status' => $status, 'terakhir_pesan' => mb_substr($pesan ?: '-', 0, 500)]);

        return ['status' => $status, 'pesan' => $pesan];
    }

    // ───────────── detak cron ─────────────

    public function detak(): void
    {
        DB::table('pengaturan_umums')->orderBy('id')->limit(1)->update(['cron_terakhir' => now()]);
    }

    public function cronTerakhir(): ?Carbon
    {
        $t = DB::table('pengaturan_umums')->orderBy('id')->value('cron_terakhir');

        return $t ? Carbon::parse($t) : null;
    }

    public function cronHidup(): bool
    {
        $t = $this->cronTerakhir();

        return $t !== null && $t->gt(now()->subMinutes(self::SELANG_CRON_MENIT));
    }

    // ───────────── cadangan database (cron cPanel kedua) ─────────────

    public function folderCadangan(): string
    {
        return storage_path('app/private/cadangan-db');
    }

    /** @return Collection<int, array{nama:string, ukuran:int, waktu:Carbon}> terbaru dulu */
    public function cadangan(): Collection
    {
        $file = glob($this->folderCadangan() . DIRECTORY_SEPARATOR . 'erp-*.sql.gz') ?: [];

        return collect($file)->map(fn ($f) => ['nama' => basename($f), 'ukuran' => (int) filesize($f), 'waktu' => Carbon::createFromTimestamp(filemtime($f))])
            ->sortByDesc(fn ($x) => $x['waktu']->timestamp)->values();
    }

    /** Cadangan terakhir dalam 26 jam & ukurannya wajar (> 1 KB; dump gagal biasanya file gzip ~20 byte). */
    public function cadanganSehat(): bool
    {
        $c = $this->cadangan()->first();

        return $c !== null && $c['waktu']->gt(now()->subHours(26)) && $c['ukuran'] > 1024;
    }

    /** Lokasi PHP untuk baris cron (PHP_BINARY di web = lsphp / php-fpm → pakai php CLI di folder yang sama). */
    public static function phpCli(): string
    {
        $bin = PHP_BINARY;
        $nama = basename($bin);
        if (str_contains($nama, 'lsphp') || str_contains($nama, 'php-fpm') || str_contains($nama, 'php-cgi')) {
            return dirname($bin) . DIRECTORY_SEPARATOR . (str_ends_with($nama, '.exe') ? 'php.exe' : 'php');
        }

        return $bin;
    }

    /** Baris siap salin untuk cPanel › Cron Jobs. @return array{jadwal:string, cadangan:string, kunci:string} */
    public function barisCron(): array
    {
        $base = base_path();
        $home = dirname($base);
        $folder = $this->folderCadangan();
        $db = (string) config('database.connections.mysql.database');
        $cnf = $home . '/.my-erp.cnf';

        return [
            'jadwal' => "cd {$base} && " . self::phpCli() . ' artisan schedule:run >> /dev/null 2>&1',
            'cadangan' => "mkdir -p {$folder} && mysqldump --defaults-extra-file={$cnf} --single-transaction --quick --no-tablespaces {$db} | gzip > {$folder}/erp-$(date +\\%Y\\%m\\%d-\\%H\\%M).sql.gz && find {$folder} -name 'erp-*.sql.gz' -mtime +" . self::SIMPAN_CADANGAN_HARI . ' -delete',
            // sekali saja di Terminal: berkas kunci mysqldump dari .env (kata sandi tidak tampil di layar)
            'kunci' => "printf '[client]\\nuser=%s\\npassword=\"%s\"\\n' \"$(grep '^DB_USERNAME=' {$base}/.env | cut -d= -f2- | tr -d '\\\"')\" \"$(grep '^DB_PASSWORD=' {$base}/.env | cut -d= -f2- | tr -d '\\\"')\" > {$cnf} && chmod 600 {$cnf}",
        ];
    }
}
