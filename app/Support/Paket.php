<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Paket modul & masa langganan per instalasi (porting Dimsum 4.53 (_rancangan/RANCANGAN-KAMUS-TIMIT-PAKET.md)).
 *
 * Satu titik pusat: izin & route milik modul yang mati selalu ditolak — juga
 * untuk Owner & Tim IT (Gate::before, User::hasPermission, middleware CekPaket).
 * Izin/route yang TIDAK ada di peta = inti (selalu aktif) → lupa memetakan
 * tidak pernah membuat fitur hilang. Data modul yang mati tidak pernah dihapus.
 *
 * Segel = HMAC APP_KEY atas isi lisensi. Mode LUNAK (keputusan Owner): segel
 * rusak → aplikasi tetap jalan memakai isi yang ada, Tim IT diberi peringatan.
 */
class Paket
{
    /** kode => [label, keterangan, butuh[]] */
    public const MODUL = [
        'stok'        => ['Stok & Gudang', 'Dashboard Stok, Stok Barang, Penyesuaian, Permintaan & Transfer, Bahan Baku, Produk Jual, Produk Toko, Semua Barang, Pemakaian Perlengkapan, Laporan Stok/Perlengkapan/Konsumsi Bahan', []],
        'penjualan'   => ['Kasir & Penjualan', 'POS (barcode, printer agent, APK BABA Kasir), Riwayat Order, laporan kasir, Pelanggan, Jenis Produk, Label Produk, Setoran Kasir, Laporan Penjualan', ['stok']],
        'antrian'     => ['Antrian', 'Cek/Kelola Antrian, Mode Produksi, Display TV (mati bawaan per cabang)', ['penjualan']],
        'loyalty'     => ['Program Loyalty', 'Program Loyalty & klaim hadiah', ['penjualan']],
        'toko_online' => ['Toko Online', 'Produk & harga online, Pesanan Online, Retur, Stok Member, RO, Pengaturan & saklar toko, Laporan per tim, dan semua layanan toko di aplikasi member (Belanja, Pesanan Saya, Toko Saya)', ['stok']],
        'produksi'    => ['Produksi Parfum', 'Resep parfum (Buat Parfum) & produksi botol di gudang', ['stok']],
        'kiriman'     => ['Kirim ke Cabang & Retur', 'Kiriman Cabang, Retur Cabang, mode Kirim ke Cabang di POS gudang', ['stok', 'penjualan']],
        'stok_opname' => ['Stok Opname', 'Stok Opname, Stok Rusak, Ganti Rugi Karyawan, Dashboard Opname, Laporan Kerugian Stok (potong gaji butuh Penggajian)', ['stok']],
        'pembelian'   => ['Pembelian', 'Purchase Order, Supplier, Dashboard PO', ['stok']],
        'keuangan'    => ['Keuangan Lanjutan', 'Transfer Antar Kas, Transfer Dana, Transaksi Berulang, Kategori Transaksi, Closing/Tutup Buku, Analisis BEP, laporan keuangan', []],
        'akuntansi'   => ['Akuntansi', 'COA, Neraca, Laba Rugi Formal, Buku Besar, Laporan Eksekutif', ['keuangan']],
        'analisis'    => ['Analisis Bisnis', 'Laporan BEP & BEP Otomatis, Simulator BEP, Jam Ramai, Laba Rugi Produksi, Cabang vs Cabang, Ranking Kasir, widget analitik dashboard', ['penjualan']],
        'sdm'         => ['SDM & Absensi', 'Karyawan, Absensi (scan, manual, wajah, device), Shift, Hari Libur, Cuti, Laporan Absensi', []],
        'penggajian'  => ['Penggajian', 'Penggajian, Pengaturan Penggajian, Laporan Penggajian', ['sdm']],
        'evaluasi'    => ['Penilaian 360°', 'Evaluasi karyawan & laporannya', ['sdm']],
        'aset'        => ['Aset', 'Manajemen Aset, penyusutan, Laporan Aset', []],
    ];

    public const PRESET = [
        'dasar'    => ['Dasar', ['stok', 'penjualan']],
        'menengah' => ['Menengah', ['stok', 'penjualan', 'toko_online', 'produksi', 'pembelian', 'stok_opname', 'keuangan', 'sdm', 'penggajian', 'loyalty']],
        'lengkap'  => ['Lengkap', null], // null = semua modul
    ];

    /** Pola nama izin → modul (urutan penting: pola khusus dulu). */
    public const IZIN = [
        'penjualan'   => ['order.*', 'pelanggan.*', 'jenis-olahan.*', 'label_cup.*', 'penjualan.*', 'laporan.penjualan.*', 'setoran_kasir.*', 'laporan.setoran_kasir.*', 'kas.buka_laci'],
        'antrian'     => ['antrian.*'],
        'loyalty'     => ['loyalty.*'],
        'toko_online' => ['toko_online.*'],
        'produksi'    => ['produksi.*'],
        'kiriman'     => ['kiriman.*', 'retur.*'],
        'stok_opname' => ['stok_opname.*'],
        'stok'        => ['stok.*', 'item.*', 'master.*', 'pemakaian_perlengkapan.*', 'laporan.stok.*', 'laporan.perlengkapan.*', 'laporan.konsumsi_bahan.*'],
        'pembelian'   => ['pembelian.*', 'supplier.*', 'po_dashboard.*', 'po.batal_bayar.*', 'kas.pilih_po.*', 'transaksi.link_po.*'],
        'keuangan'    => ['transfer_antar_kas.*', 'setoran.*', 'recurring.*', 'kategori.*', 'closing.*', 'bep.*', 'laporan.keuangan.*', 'laporan.saldo_kas.*',
            'laporan.transfer_dana.*', 'laporan.kategori.*', 'laporan.audit_bukti.*', 'laporan.setoran_harian.*'],
        'akuntansi'   => ['coa.*', 'laporan.neraca.*', 'laporan.laba_rugi_formal.*', 'laporan.buku_besar.*', 'laporan.eksekutif.*'],
        'analisis'    => ['laporan.bep.*', 'laporan.bep_otomatis.*', 'laporan.simulator.*', 'laporan.jam_ramai.*', 'laporan.laba_rugi.*', 'laporan.cabang_vs_cabang.*',
            'laporan.ranking_kasir.*', 'dashboard.analytics.*'],
        'penggajian'  => ['penggajian.*', 'pengaturan_penggajian', 'laporan.hr_penggajian.*'],
        'evaluasi'    => ['evaluasi.*', 'laporan.hr_evaluasi.*'],
        'sdm'         => ['karyawan.*', 'absensi.*', 'scan_absensi', 'dashboard_absensi', 'laporan_absensi', 'registrasi_wajah', 'hapus_absensi', 'hapus_log_absensi',
            'kelola_shift', 'lihat_shift', 'kelola_hari_libur', 'cuti.*', 'laporan.hr_absensi.*'],
        'aset'        => ['aset.*', 'laporan.aset.*'],
    ];

    /** Pola nama route → modul (alamat langsung ke modul yang mati → halaman "tidak termasuk paket"). API aplikasi member: lihat CekPaketMember. */
    public const ROUTE = [
        'penjualan'   => ['penjualan.*', 'pos.*', 'pelanggan.*', 'master.jenis-olahan.*', 'label-cup.*', 'setoran-kasir.*', 'laporan.setoran-kasir*', 'laporan.penjualan*', 'kas.log-buka-laci'],
        'antrian'     => ['antrian.*'],
        'loyalty'     => ['loyalty-program.*', 'loyalty-klaim.*'],
        'toko_online' => ['toko-online.*'],
        'produksi'    => ['produksi.*'],
        'kiriman'     => ['kiriman.*', 'retur-cabang.*'],
        'stok_opname' => ['stok-opname.*'],
        'stok'        => ['stok.*', 'stock-request.*', 'stock-transfer.*', 'master.bahan-baku.*', 'master.produk-jual.*', 'master.resep-bumbu.*', 'item.*', 'produk-toko.*',
            'pemakaian-perlengkapan.*', 'laporan.stok*', 'laporan.perlengkapan*', 'laporan.konsumsi-bahan*', 'api.stok.*'],
        'pembelian'   => ['pembelian.*', 'supplier.*', 'keuangan.po-pending-list'],
        'keuangan'    => ['transfer-antar-kas.*', 'setoran.*', 'recurring.*', 'kategori-transaksi.*', 'closing.*', 'bep.*', 'keuangan.laporan', 'laporan.keuangan.*',
            'laporan.saldo-kas*', 'laporan.setoran', 'laporan.kategori*', 'laporan.audit-bukti*', 'laporan.setoran-harian*'],
        'akuntansi'   => ['coa.*', 'laporan.neraca*', 'laporan.laba-rugi-formal*', 'laporan.buku-besar*', 'laporan.eksekutif*'],
        'analisis'    => ['laporan.bep', 'laporan.bep.*', 'laporan.bep-otomatis*', 'laporan.simulator-bep*', 'laporan.jam-ramai*', 'laporan.laba-rugi', 'laporan.laba-rugi.*',
            'laporan.cabang-vs-cabang*'],
        'penggajian'  => ['penggajian.*', 'pengaturan.penggajian*', 'laporan.hr.penggajian*'],
        'evaluasi'    => ['evaluasi.*', 'laporan.hr.evaluasi*'],
        'sdm'         => ['karyawan.*', 'absensi.*', 'absensi-saya*', 'absen-device.*', 'face-attendance.*', 'face-registration.*', 'api.face-data*', 'shift.*',
            'hari-libur.*', 'cuti.*', 'laporan.absensi*', 'laporan.hr.absensi*'],
        'aset'        => ['aset.*', 'laporan.aset*'],
    ];

    private static ?array $cache = null;

    /** izin => modul|false (peta tetap, aman disimpan sepanjang proses) */
    private static array $memoIzin = [];

    public static function siap(): bool
    {
        try {
            return Schema::hasTable('lisensi');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Baris lisensi (tanpa tabel / baris = semua modul aktif, tanpa batas). */
    public static function data(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $row = self::siap() ? DB::table('lisensi')->orderBy('id')->first() : null;
        $modul = $row ? (json_decode((string) $row->modul, true) ?: []) : array_keys(self::MODUL);

        return self::$cache = [
            'ada'           => (bool) $row,
            'modul'         => array_values(array_intersect(array_keys(self::MODUL), $modul)),
            'aktif_sampai'  => $row?->aktif_sampai ? Carbon::parse($row->aktif_sampai)->startOfDay() : null,
            'masa_tenggang' => (int) ($row->masa_tenggang_hari ?? 7),
            'segel_valid'   => ! $row || hash_equals(self::segel((array) $row), (string) $row->segel),
            'diubah_at'     => $row?->updated_at,
            'diubah_by'     => $row?->diubah_by,
        ];
    }

    public static function lupakan(): void
    {
        self::$cache = null;
    }

    public static function aktif(string $modul): bool
    {
        return ! isset(self::MODUL[$modul]) || in_array($modul, self::data()['modul'], true);
    }

    public static function modulUntukIzin(string $izin): ?string
    {
        if (array_key_exists($izin, self::$memoIzin)) {
            return self::$memoIzin[$izin] ?: null;
        }
        foreach (self::IZIN as $modul => $pola) {
            foreach ($pola as $p) {
                if (Str::is($p, $izin)) {
                    return self::$memoIzin[$izin] = $modul;
                }
            }
        }
        self::$memoIzin[$izin] = false;

        return null;
    }

    public static function modulUntukRoute(?string $nama): ?string
    {
        if (! $nama) {
            return null;
        }
        foreach (self::ROUTE as $modul => $pola) {
            foreach ($pola as $p) {
                if (Str::is($p, $nama)) {
                    return $modul;
                }
            }
        }

        return null;
    }

    /** Izin ini termasuk paket? (izin di luar peta = inti = selalu boleh) */
    public static function izinDiizinkan(string $izin): bool
    {
        $m = self::modulUntukIzin($izin);

        return $m === null || self::aktif($m);
    }

    /** Lengkapi dengan modul yang dibutuhkan (menyalakan Produksi → Stok ikut nyala). */
    public static function denganKetergantungan(array $modul): array
    {
        $hasil = array_values(array_intersect(array_keys(self::MODUL), $modul));
        do {
            $tambah = false;
            foreach ($hasil as $m) {
                foreach (self::MODUL[$m][2] as $butuh) {
                    if (! in_array($butuh, $hasil, true)) {
                        $hasil[] = $butuh;
                        $tambah = true;
                    }
                }
            }
        } while ($tambah);

        return array_values(array_intersect(array_keys(self::MODUL), $hasil));
    }

    /** Nama paket kalau isi modul sama persis dengan salah satu preset, selain itu "Kustom". */
    public static function namaPaket(?array $modul = null): string
    {
        $modul ??= self::data()['modul'];
        sort($modul);
        foreach (self::PRESET as [$nama, $isi]) {
            $isi ??= array_keys(self::MODUL);
            sort($isi);
            if ($isi === $modul) {
                return $nama;
            }
        }

        return 'Kustom';
    }

    /** Peringatan mulai berapa hari sebelum langganan berakhir. */
    public const HARI_PERINGATAN = 14;

    /**
     * Status langganan hari ini. "Aktif sampai" = hari terakhir masih berlaku;
     * sesudahnya masa tenggang N hari; lewat itu baca-saja.
     *
     * @return array{kode: string, aktif_sampai: ?Carbon, batas_tenggang: ?Carbon, sisa_hari: ?int}
     */
    public static function statusLangganan(?Carbon $kini = null): array
    {
        $d = self::data();
        $sampai = $d['aktif_sampai'];
        if (! $sampai) {
            return ['kode' => 'tanpa_batas', 'aktif_sampai' => null, 'batas_tenggang' => null, 'sisa_hari' => null];
        }
        $hari = ($kini ?? now())->copy()->startOfDay();
        $batas = $sampai->copy()->addDays($d['masa_tenggang']);
        if ($hari->lte($sampai)) {
            $sisa = (int) $hari->diffInDays($sampai);
            $kode = $sisa <= self::HARI_PERINGATAN ? 'segera' : 'aktif';
        } elseif ($hari->lte($batas)) {
            $sisa = (int) $hari->diffInDays($batas);
            $kode = 'tenggang';
        } else {
            $sisa = 0;
            $kode = 'baca_saja';
        }

        return ['kode' => $kode, 'aktif_sampai' => $sampai, 'batas_tenggang' => $batas, 'sisa_hari' => $sisa];
    }

    /**
     * Mode lunak: segel rusak → beri tahu Tim IT aktif, paling banyak sekali per hari.
     *
     * @return int jumlah notifikasi
     */
    public static function peringatkanSegelRusak(): int
    {
        if (! self::siap() || self::data()['segel_valid']) {
            return 0;
        }
        $row = DB::table('lisensi')->orderBy('id')->first();
        if ($row && $row->peringatan_segel_at === today()->toDateString()) {
            return 0;
        }
        $tim = \App\Models\User::where('role', \App\Enums\RoleUser::Teknisi->value)->where('is_active', true)->get();
        \App\Services\NotificationService::send($tim, new \App\Notifications\PaketNotification(
            'Segel lisensi tidak cocok', 'Isi Paket & Langganan diubah di luar aplikasi. Periksa lalu simpan ulang.', route('paket.index'), 'danger'));
        DB::table('lisensi')->where('id', $row->id)->update(['peringatan_segel_at' => today()->toDateString()]);

        return $tim->count();
    }

    public static function bacaSaja(?Carbon $kini = null): bool
    {
        return self::statusLangganan($kini)['kode'] === 'baca_saja';
    }

    public static function segel(array $row): string
    {
        $modul = json_decode((string) ($row['modul'] ?? '[]'), true) ?: [];
        sort($modul);
        $isi = implode('|', [
            implode(',', $modul),
            $row['aktif_sampai'] ? Carbon::parse($row['aktif_sampai'])->toDateString() : '-',
            (int) ($row['masa_tenggang_hari'] ?? 7),
        ]);

        return hash_hmac('sha256', 'lisensi|' . $isi, (string) config('app.key'));
    }

    /**
     * Simpan paket (hanya dipanggil dari halaman Paket & Langganan oleh Tim IT,
     * atau migration/seeder). Ketergantungan otomatis dilengkapi.
     *
     * @return array{lama: array, baru: array}
     */
    public static function simpan(array $modul, ?string $aktifSampai, int $masaTenggang, ?int $userId): array
    {
        $lama = self::data();
        $modul = self::denganKetergantungan($modul);
        $row = [
            'modul'              => json_encode($modul),
            'aktif_sampai'       => $aktifSampai ? Carbon::parse($aktifSampai)->toDateString() : null,
            'masa_tenggang_hari' => max(0, min(60, $masaTenggang)),
        ];
        $row['segel'] = self::segel($row);
        $row['diubah_by'] = $userId;
        $row['updated_at'] = now();
        $id = DB::table('lisensi')->orderBy('id')->value('id');
        if ($id) {
            DB::table('lisensi')->where('id', $id)->update($row);
        } else {
            DB::table('lisensi')->insert($row + ['created_at' => now()]);
        }
        self::lupakan();

        return ['lama' => ['modul' => $lama['modul'], 'aktif_sampai' => $lama['aktif_sampai']?->toDateString(), 'masa_tenggang' => $lama['masa_tenggang']],
            'baru' => ['modul' => $modul, 'aktif_sampai' => $row['aktif_sampai'], 'masa_tenggang' => $row['masa_tenggang_hari']]];
    }
}
