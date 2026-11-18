<?php

namespace App\Models;

use App\Enums\RoleUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * 4.50 Kamus Menu — 1 baris = penjelasan 1 menu (atau 1 topik perbandingan).
 * Isi ditulis di KamusMenuSeeder. Hak lihat mengikuti izin menu itu sendiri.
 */
class KamusMenu extends Model
{
    protected $table = 'kamus_menus';

    protected $fillable = [
        'slug', 'judul', 'kelompok', 'urutan', 'letak', 'ringkasan', 'konten',
        'kata_kunci', 'izin', 'route_names', 'terkait', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'izin'        => 'array',
            'route_names' => 'array',
            'terkait'     => 'array',
            'aktif'       => 'boolean',
        ];
    }

    /** Urutan kelompok = urutan bagian di sidebar. */
    public const KELOMPOK = [
        'Cara Melakukan',
        'Dashboard',
        'Penjualan',
        'Toko Online',
        'Stok & Gudang',
        'Pembelian',
        'Keuangan',
        'SDM & Karyawan',
        'Aset',
        'Laporan',
        'Pengaturan',
        'Bantuan',
        'Keamanan',
    ];

    public const CACHE_KEY = 'kamus_menu:semua:v1';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Semua entri aktif (dipakai pencarian & tombol header — ±100 baris, aman dimuat semua). */
    public static function semuaAktif(): Collection
    {
        try {
            return Cache::remember(self::CACHE_KEY, 3600, fn () => static::where('aktif', true)->get());
        } catch (\Throwable) {
            return collect();
        }
    }

    /** Boleh dibuka user ini? izin = daftar "salah satu cukup". */
    public function bolehDilihat(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        $izin = $this->izin ?? [];
        // Entri khusus Tim IT (menu Paket & Langganan, Tim IT) — Owner pelanggan tidak melihatnya
        if (in_array('@teknisi', $izin, true)) {
            return $user->isTeknisi();
        }
        if (! $this->termasukPaket()) {
            return false;
        }
        if ($user->aksesPenuh()) {
            return true;
        }
        if ($izin === []) {
            return true;
        }
        foreach ($izin as $i) {
            if ($i === '@owner') {
                continue;
            }
            if ($i === '@semua_cabang') {
                if ($user->canAccessAllBranches()) {
                    return true;
                }
                continue;
            }
            if ($user->hasPermission($i)) {
                return true;
            }
        }

        return false;
    }

    /** Menu entri ini termasuk paket? (semua izin nyata / semua route di luar paket → tidak) */
    public function termasukPaket(): bool
    {
        $izin = array_values(array_filter($this->izin ?? [], fn ($i) => ! str_starts_with($i, '@')));
        if ($izin) {
            foreach ($izin as $i) {
                if (\App\Support\Paket::izinDiizinkan($i)) {
                    return true;
                }
            }

            return false;
        }
        $route = $this->route_names ?? [];
        if (! $route) {
            return true;
        }
        foreach ($route as $r) {
            $m = \App\Support\Paket::modulUntukRoute(str_replace('*', 'x', $r));
            if ($m === null || \App\Support\Paket::aktif($m)) {
                return true;
            }
        }

        return false;
    }

    /** Entri yang cocok dgn halaman yang sedang dibuka (untuk tombol 📖 di header). */
    public static function untukRoute(?string $routeName, ?User $user): ?self
    {
        if (! $routeName) {
            return null;
        }
        foreach (static::semuaAktif() as $k) {
            foreach ($k->route_names ?? [] as $pola) {
                if (Str::is($pola, $routeName) && $k->bolehDilihat($user)) {
                    return $k;
                }
            }
        }

        return null;
    }

    /** Teks izin yang dibaca orang ("Lihat Transfer Stok" dst), bukan kode izin. */
    public function izinTeks(): array
    {
        $izin = $this->izin ?? [];
        $nama = [];
        $kode = array_values(array_filter($izin, fn ($i) => ! str_starts_with($i, '@')));
        $label = $kode ? DB::table('permissions')->whereIn('name', $kode)->pluck('display_name', 'name') : collect();
        foreach ($izin as $i) {
            $nama[] = match ($i) {
                '@owner'        => 'Khusus Owner',
                '@teknisi'      => 'Khusus Tim IT',
                '@semua_cabang' => 'Role yang boleh melihat Semua Cabang (Owner, Admin Pusat)',
                default         => ($label[$i] ?? $i),
            };
        }

        return $nama;
    }

    public function getKontenHtmlAttribute(): string
    {
        try {
            $converter = new GithubFlavoredMarkdownConverter([
                'html_input'         => 'strip',
                'allow_unsafe_links' => false,
            ]);

            return $converter->convert($this->konten)->getContent();
        } catch (\Throwable) {
            return e($this->konten);
        }
    }

    /**
     * Cari entri. Tiap kata dicari di judul (bobot 10), kata kunci (6), letak/ringkasan (4),
     * isi (1). Utamakan entri yang memuat SEMUA kata; kalau tidak ada, entri yang memuat sebagian.
     *
     * @return Collection<int, array{kamus: self, skor: int, cuplikan: ?string}>
     */
    public static function cari(string $q, Collection $entri): Collection
    {
        $kata = collect(preg_split('/\s+/', Str::lower(trim($q))))
            ->map(fn ($k) => trim($k, " \t,.?!:;\"'()"))
            ->filter(fn ($k) => mb_strlen($k) >= 2)
            ->reject(fn ($k) => in_array($k, ['di', 'ke', 'ya', 'yg', 'dan', 'atau', 'cara', 'bagaimana', 'gimana', 'apa', 'mau', 'ingin', 'bisa', 'untuk', 'dari', 'yang', 'saya', 'kita', 'menu'], true))
            ->unique()->values();
        if ($kata->isEmpty()) {
            return collect();
        }

        $hasil = $entri->map(function (self $k) use ($kata) {
            $judul = Str::lower($k->judul);
            $kunci = Str::lower((string) $k->kata_kunci);
            $ring  = Str::lower($k->letak . ' ' . $k->ringkasan);
            $isi   = Str::lower($k->konten);
            $skor = 0;
            $kena = 0;
            foreach ($kata as $w) {
                $s = 0;
                if (str_contains($judul, $w)) { $s += 10; }
                if (str_contains($kunci, $w)) { $s += 6; }
                if (str_contains($ring, $w))  { $s += 4; }
                if (str_contains($isi, $w))   { $s += min(3, substr_count($isi, $w)); }
                if ($s > 0) { $kena++; }
                $skor += $s;
            }

            return ['kamus' => $k, 'skor' => $skor, 'kena' => $kena, 'cuplikan' => static::cuplikan($k->konten, $kata)];
        })->filter(fn ($h) => $h['skor'] > 0);

        $semuaKata = $hasil->filter(fn ($h) => $h['kena'] === $kata->count());
        $pakai = $semuaKata->isNotEmpty() ? $semuaKata : $hasil;

        return $pakai->sortByDesc('skor')->values();
    }

    /** 1 baris isi yang memuat kata dicari (tanpa tanda Markdown), untuk ditampilkan di hasil cari. */
    protected static function cuplikan(string $konten, Collection $kata): ?string
    {
        foreach (preg_split('/\R/', $konten) as $baris) {
            $bersih = trim(preg_replace('/[*#`|>_]+/', ' ', $baris));
            $bersih = preg_replace('/\s+/', ' ', $bersih);
            if ($bersih === '' || preg_match('/^[-:\s]+$/', $bersih)) {
                continue;
            }
            $kecil = Str::lower($bersih);
            foreach ($kata as $w) {
                if (str_contains($kecil, $w)) {
                    return Str::limit($bersih, 180);
                }
            }
        }

        return null;
    }
}
