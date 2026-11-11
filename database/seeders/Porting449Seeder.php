<?php

namespace Database\Seeders;

use App\Models\Panduan;
use App\Models\Permission;
use App\Models\Tooltip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 4.49 — porting dari erp-coffeeshop (CLAUDE.md coffeeshop 4.50/4.51/12.15):
 * Label Produk gambar (Tahap D), POS HP (Tahap C), Produksi (B1),
 * Kirim ke Cabang (B2), Retur Cabang (B3).
 *
 * ADDITIVE & idempotent — aman di production: panduan memakai penanda
 * (`<!-- ... -->`) supaya tidak dobel, tooltip updateOrCreate per key.
 * TIDAK menyentuh centangan role yang sudah ada (izin baru ditangani per tahap,
 * hanya dicentang saat izin itu pertama kali dibuat).
 * Jalankan SETELAH LabelProdukSeeder (yang menulis ulang panduan label-produk).
 */
class Porting449Seeder extends Seeder
{
    /** Izin baru 4.49 (juga di-spread ke PermissionSeeder utk instalasi baru). */
    public const PERMISSIONS = [
        ['name' => 'produksi.view',         'display_name' => 'Lihat Produksi (bahan setengah jadi di gudang)', 'group' => 'stok'],
        ['name' => 'produksi.create',       'display_name' => 'Catat Produksi',                                 'group' => 'stok'],
        ['name' => 'produksi.batalkan',     'display_name' => 'Batalkan Produksi',                              'group' => 'stok'],
        ['name' => 'produksi.resep.kelola', 'display_name' => 'Kelola Resep Produksi',                          'group' => 'stok'],
        ['name' => 'kiriman.view',          'display_name' => 'Lihat Kiriman Cabang (Gudang → cabang)',        'group' => 'stok'],
        ['name' => 'kiriman.terima',        'display_name' => 'Terima / Tolak Kiriman dari Gudang',             'group' => 'stok'],
        ['name' => 'retur.view',            'display_name' => 'Lihat Retur Cabang',                             'group' => 'stok'],
        ['name' => 'retur.create',          'display_name' => 'Buat / Batalkan Retur ke Gudang',                'group' => 'stok'],
        ['name' => 'retur.terima',          'display_name' => 'Terima / Tolak Retur dari Cabang (Gudang)',      'group' => 'stok'],
    ];

    /**
     * Centangan bawaan — HANYA dipasang saat izin itu pertama kali dibuat,
     * supaya centangan yang sudah diubah Owner tidak pernah ditimpa. Juga
     * di-spread ke RolePermissionSeeder utk instalasi baru. Owner selalu bisa (Gate::before).
     */
    public const ROLE_DEFAULTS = [
        'admin_pusat'       => ['produksi.view', 'produksi.create', 'produksi.batalkan', 'produksi.resep.kelola', 'kiriman.view', 'kiriman.terima', 'retur.view', 'retur.create', 'retur.terima'],
        'admin_gudang'      => ['produksi.view', 'produksi.create', 'produksi.batalkan', 'produksi.resep.kelola', 'kiriman.view', 'retur.view', 'retur.terima'],
        'manajer_cabang'    => ['kiriman.view', 'kiriman.terima', 'retur.view', 'retur.create'],
        'kasir'             => ['kiriman.view', 'kiriman.terima', 'retur.view', 'retur.create'],
        'operator_produksi' => ['produksi.view', 'produksi.create'],
    ];

    public function run(): void
    {
        $baru = $this->izin();
        $this->labelGambar();
        $this->posHp();
        $this->produksi();
        $this->kiriman();
        $this->retur();

        if ($baru) {
            $this->command?->info('   Izin baru dibuat: ' . implode(', ', $baru));
        }

        $this->command?->info('✅ Porting 4.49: panduan & tooltip (idempotent).');
    }

    /**
     * Buat izin yang belum ada; role bawaan hanya dicentang untuk izin yang BARU dibuat.
     *
     * @return string[] nama izin yang baru dibuat
     */
    private function izin(): array
    {
        $baru = [];
        foreach (self::PERMISSIONS as $p) {
            $perm = Permission::firstOrCreate(['name' => $p['name']], ['display_name' => $p['display_name'], 'group' => $p['group']]);
            if (! $perm->wasRecentlyCreated) {
                continue;
            }
            $baru[] = $p['name'];
            $rows = [];
            foreach (self::ROLE_DEFAULTS as $role => $names) {
                if (in_array($p['name'], $names, true)) {
                    $rows[] = ['role' => $role, 'permission_id' => $perm->id];
                }
            }
            DB::table('role_permissions')->insertOrIgnore($rows);
        }

        Cache::forget('all_permission_names');
        foreach (array_keys(self::ROLE_DEFAULTS) as $role) {
            Cache::forget("role_permissions_{$role}");
        }

        return $baru;
    }

    // ===================== Tahap B1 — Produksi =====================

    private function produksi(): void
    {
        Panduan::updateOrCreate(['slug' => 'produksi'], [
            'judul'  => 'Produksi Bahan Setengah Jadi',
            'konten' => self::PANDUAN_PRODUKSI,
            'modul'  => 'stok',
            'urutan' => 45,
            'aktif'  => true,
        ]);

        foreach ([
            ['key' => 'produksi.barang_hasil', 'title' => 'Barang hasil',
                'content' => 'Barang setengah jadi yang dibuat, mis. Isian Ayam atau Chili Oil. Daftarkan dulu di Bahan Baku & Kemasan milik gudang ini (satuan kg/liter/pcs).'],
            ['key' => 'produksi.qty_hasil_resep', 'title' => 'Hasil per resep',
                'content' => 'Jumlah barang hasil dari 1 kali resep (dalam satuan barang hasil). Mis. resep ini menghasilkan 2 kg isian.'],
            ['key' => 'produksi.satuan_takaran', 'title' => 'Satuan takaran',
                'content' => 'Boleh beda dengan satuan stok bahan: g/kg/ons dan ml/liter dikonversi otomatis (mis. 250 g dari bahan yang stoknya kg = 0,25 kg).'],
            ['key' => 'produksi.qty_rencana', 'title' => 'Rencana dibuat',
                'content' => 'Jumlah yang direncanakan. Bahan dipotong dari stok gudang sebanyak resep × rencana ini.'],
            ['key' => 'produksi.qty_hasil', 'title' => 'Hasil nyata',
                'content' => 'Jumlah yang benar-benar jadi (boleh beda dari rencana, mis. rencana 5 kg jadi 4,8 kg). Stok barang hasil bertambah sebanyak angka ini.'],
            ['key' => 'produksi.modal', 'title' => 'Modal per unit',
                'content' => 'Otomatis = total biaya bahan (FIFO) ÷ hasil nyata. Bisa diisi manual kalau perlu. Modal ini dipakai sebagai HPP saat barang hasil terpakai di penjualan.'],
            ['key' => 'produksi.batalkan', 'title' => 'Batalkan produksi',
                'content' => 'Stok hasil ditarik dan bahan dikembalikan. Hanya bisa kalau stok hasil di gudang masih cukup (belum terpakai/terkirim).'],
        ] as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'], 'modul' => 'stok', 'aktif' => true, 'urutan' => 80 + $i,
            ]);
        }
    }

    public const PANDUAN_PRODUKSI = <<<'MARKDOWN'
## Tujuan
Mencatat pembuatan **bahan setengah jadi** di gudang — mis. isian ayam, saus, chili oil, adonan — dari bahan baku. Saat produksi disimpan:
- stok bahan baku di gudang **berkurang** sesuai resep (modal FIFO),
- stok barang hasil **bertambah** sebanyak hasil nyata, dengan **modal per unit** = biaya bahan ÷ hasil nyata.

Barang hasil lalu bisa dipakai di **komposisi Produk Jual**, dikirim ke cabang, atau dijual. HPP penjualan otomatis memakai modal produksi ini.

Produksi hanya dicatat di cabang bertipe **Gudang Pusat**. Data barang terpisah per cabang, jadi barang hasil dan bahannya harus barang **milik gudang itu**.

## Langkah-langkah

### 1. Daftarkan barang hasil (sekali)
Di **Bahan Baku & Kemasan** (cabang aktif = gudang), tambahkan barang hasil seperti bahan biasa, mis. "Isian Ayam" satuan **kg**.

### 2. Buat Resep Produksi (sekali)
1. Buka **Stok & Gudang → Produksi → tab Resep Produksi → Resep Baru**.
2. Pilih **Barang hasil** dan isi **Hasil per resep** (mis. 2 kg).
3. Tambahkan bahan beserta **takaran** dan **satuan** (g/kg/ons/ml/liter/pcs). Takaran gram dari bahan yang stoknya kg dikonversi otomatis.
4. Lihat **Perkiraan modal per unit** di bawah tabel, lalu **Simpan Resep**.

### 3. Catat produksi (setiap kali memasak)
1. **Produksi → Catat Produksi**, pilih barang yang dibuat.
2. Isi **Rencana dibuat** — tabel menampilkan kebutuhan bahan dan stok gudang (merah = kurang).
3. Setelah selesai masak isi **Hasil nyata** (mis. rencana 5 kg, jadi 4,8 kg).
4. Modal per unit dihitung otomatis; nyalakan **Isi modal per unit secara manual** kalau perlu.
5. **Simpan Produksi**.

### 4. Batalkan (kalau salah input)
Buka detail produksi → isi alasan → **Batalkan Produksi**. Stok hasil ditarik dan bahan dikembalikan dengan modal semula. Hanya bisa kalau stok hasil di gudang masih cukup (belum terpakai/terkirim) — kalau sudah terpakai, koreksi lewat **Penyesuaian Stok**.

## Catatan Penting
- Resep Produksi **berbeda** dengan komposisi Produk Jual: resep produksi dipakai saat **memasak**, komposisi dipotong saat **menu terjual**.
- Perkiraan biaya di form memakai harga FIFO bahan (stok habis → harga PO terakhir, belum pernah PO → Harga Standar). Angka pasti dihitung saat disimpan.
- **Harga Standar** barang hasil hanya diisi otomatis kalau masih kosong — angka yang sudah Anda isi tidak ditimpa.
- Izin: Lihat/Catat Produksi, Batalkan Produksi, Kelola Resep Produksi — diatur di **Role & Hak Akses**.

## Troubleshooting
- **Menu Produksi kosong / ada peringatan gudang?** Produksi butuh cabang bertipe Gudang Pusat yang Anda kelola.
- **Barang tidak muncul di pilihan resep?** Pastikan barang itu milik gudang (pilih gudang di pojok kanan atas lalu cek di Bahan Baku & Kemasan) dan tipenya Bahan Baku/Kemasan/Tambahan Gratis.
- **"Stok bahan di gudang tidak cukup"?** Pesan menyebut semua bahan yang kurang — tambah stok lewat Pembelian atau Penyesuaian Stok dulu.
- **Perkiraan biaya "harga kosong"?** Bahan belum punya stok berharga, PO, maupun Harga Standar — isi Harga Standar di Bahan Baku & Kemasan.
MARKDOWN;

    // ===================== Tahap B2 — Kiriman Cabang =====================

    private function kiriman(): void
    {
        Panduan::updateOrCreate(['slug' => 'kiriman-cabang'], [
            'judul'  => 'Kiriman Cabang (Gudang → Cabang)',
            'konten' => self::PANDUAN_KIRIMAN,
            'modul'  => 'stok',
            'urutan' => 46,
            'aktif'  => true,
        ]);

        $p = Panduan::where('slug', 'pos')->first();
        if ($p && ! str_contains((string) $p->konten, '<!-- KIRIM-CABANG-449 -->')) {
            $p->update(['konten' => rtrim((string) $p->konten) . "\n\n" . self::BAGIAN_POS_KIRIM]);
        }

        foreach ([
            ['key' => 'cabang.kirim_cabang_aktif', 'modul' => 'cabang', 'title' => 'Kirim ke Cabang di POS',
                'content' => 'Nyalakan supaya POS gudang ini punya pilihan Jenis Pembeli → Kirim ke Cabang (barang dikirim ke cabang, cabang menekan Terima). Bawaan mati: POS gudang seperti biasa.'],
            ['key' => 'pos.customer_type', 'modul' => 'pos', 'title' => 'Jenis Pembeli',
                'content' => 'Penjualan biasa = pelanggan bayar di sini. Kirim ke Cabang = Gudang mengirim barang ke cabang sendiri; tanpa pembayaran di sini, uang dipotong dari kas cabang saat cabang menekan Terima.'],
            ['key' => 'pos.outlet_tujuan', 'modul' => 'pos', 'title' => 'Cabang Tujuan',
                'content' => 'Cabang yang akan menerima kiriman. Barang masuk ke stok cabang itu setelah cabang menekan Terima di menu Kiriman Cabang.'],
            ['key' => 'kiriman.qty_diterima', 'modul' => 'stok', 'title' => 'Jumlah sampai',
                'content' => 'Isi jumlah yang benar-benar datang. Kalau kurang dari yang dikirim, sisanya kembali ke stok Gudang dan total yang dibayar ikut turun.'],
            ['key' => 'kiriman.kas', 'modul' => 'stok', 'title' => 'Bayar dari kas',
                'content' => 'Kas cabang yang membayar kiriman ini (bawaan Kas Tunai). Saldo kas boleh menjadi minus — ada peringatan kalau saldo tidak cukup.'],
            ['key' => 'kiriman.tolak', 'modul' => 'stok', 'title' => 'Tolak kiriman',
                'content' => 'Barang tidak jadi diterima (salah kirim, rusak semua). Semua barang kembali ke stok Gudang, tidak ada uang yang berpindah.'],
        ] as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'], 'modul' => $t['modul'], 'aktif' => true, 'urutan' => 90 + $i,
            ]);
        }
    }

    public const BAGIAN_POS_KIRIM = <<<'MARKDOWN'
<!-- KIRIM-CABANG-449 -->
## Kirim ke Cabang (khusus POS Gudang Pusat)
Di POS dengan cabang aktif = **Gudang Pusat** (setelah saklar **Aktifkan Kirim ke Cabang di POS** dinyalakan di Cabang → Edit) ada pilihan **Jenis Pembeli → Kirim ke Cabang** + **Cabang Tujuan**. Bagian pembayaran dan Save Bill disembunyikan, tombolnya menjadi **KIRIM KE CABANG**: stok Gudang langsung berkurang, lalu cabang menekan **Terima** di menu **Kiriman Cabang**. Lihat panduan **Kiriman Cabang**.
MARKDOWN;

    public const PANDUAN_KIRIMAN = <<<'MARKDOWN'
## Tentang
Gudang Pusat **menjual** barang ke cabang sendiri, dengan harga yang boleh dinaikkan (gudang ambil untung) atau sama dengan modal (gudang hanya distribusi). Cabang tujuan mengecek barang lalu menekan **Terima**.

| Saat | Yang terjadi |
|---|---|
| Gudang klik **Kirim ke Cabang** di POS | Stok Gudang berkurang. Status: *Menunggu diterima*. Belum ada uang & belum dihitung di laporan. |
| Cabang klik **Terima** | Stok masuk ke barang milik cabang (modal = harga kiriman), kas cabang berkurang, kas tunai & pendapatan Gudang bertambah (tanggal Terima). |
| Cabang klik **Tolak** / Gudang **Batalkan** | Semua barang kembali ke stok Gudang dengan modal semula. Tidak ada uang yang berpindah. |

Ini cara **tambahan** — Transfer Stok dan PO ke Gudang tetap bisa dipakai seperti biasa.

## Persiapan (sekali per barang)
0. **Nyalakan fiturnya** (sekali): **Cabang → Edit** Gudang Pusat → kotak Config POS → **Aktifkan Kirim ke Cabang di POS** → Simpan. Bawaannya mati — selama mati, POS gudang tampil seperti biasa tanpa pilihan Jenis Pembeli.
1. Di **Produk Jual milik Gudang**, buat produk untuk barang yang mau dikirim, mis. "Isian Ayam (kirim ke cabang)", dengan harga kirimnya.
2. Isi **komposisinya** dengan barang yang akan masuk ke stok cabang, mis. Isian Ayam 1 kg. Yang masuk ke cabang = bahan di komposisi × jumlah kiriman. Produk tanpa komposisi tidak bisa dikirim.
3. Gudang wajib punya **Kas Tunai** aktif (pendapatan kiriman), cabang tujuan minimal 1 kas aktif.

## Barang di cabang (Kode Item)
Data barang terpisah per cabang. Saat Terima, barang masuk ke barang milik cabang yang **Kode Item-nya sama** dengan barang Gudang. Kalau cabang belum punya, barangnya **dibuat otomatis** — halaman Terima menampilkan daftar "barang baru yang akan dibuat". Kalau ternyata cabang sudah punya barang yang sama dengan kode lain, samakan Kode Item-nya dulu sebelum Terima supaya stoknya tidak terpisah.

## Cara Pakai

### Mengirim (di Gudang)
1. Pilih cabang **Gudang Pusat** di pojok kanan atas, buka **POS**.
2. Pilih **Jenis Pembeli → Kirim ke Cabang**, lalu **Cabang Tujuan**.
3. Masukkan barang seperti biasa, klik **KIRIM KE CABANG**.

### Menerima (di cabang)
1. Menu **Stok & Gudang → Kiriman Cabang** → tab **Barang Masuk** (angka merah = kiriman yang menunggu).
2. Klik **Cek & Terima**, cocokkan barang yang datang.
3. Kalau ada yang kurang, ubah **Jumlah sampai** (mis. 48 dari 50). Sisanya kembali ke stok Gudang dan total yang dibayar ikut turun.
4. Pilih **Bayar dari kas** (bawaan Kas Tunai) lalu klik **Terima**.

Barang tidak jadi diterima (salah kirim, rusak semua)? Isi alasan lalu klik **Tolak**.

### Membatalkan (di Gudang)
Selama belum diterima: buka kiriman di tab **Barang Keluar** → isi alasan → **Batalkan Kiriman**.

## Laporan
- Penjualan ke cabang dihitung sebagai pendapatan **Gudang** sejak diterima. Kiriman yang belum diterima tidak dihitung di laporan mana pun.
- Di **Semua Cabang** pada Laporan Transaksi/Produk/Kategori dan di **Tutup Buku Keseluruhan**, kiriman ke cabang sendiri tidak dihitung supaya tidak dobel (cabang juga menjual hasilnya ke pelanggan). Laporan lain tetap menghitungnya.
- Di cabang, pembayaran kiriman tercatat sebagai pengeluaran **Pembelian Bahan** (bukan beban di Laba Rugi — modalnya masuk ke HPP saat barang terjual).

## Peringatan
- Kiriman yang **sudah diterima tidak bisa dibatalkan atau diedit**. Barang basi, rusak, atau kelebihan → gunakan **Retur** dari cabang tujuan.
- Kiriman **tidak bisa disimpan sebagai Save Bill** — langsung klik Kirim.
- Keranjang yang diisi dari **Pesanan PO dari Cabang** tidak bisa dikirim lewat Kirim ke Cabang (cabang menerimanya lewat PO).

## Troubleshooting
- **Pilihan Kirim ke Cabang tidak ada di POS?** Cabang aktif harus bertipe Gudang Pusat **dan** saklar **Aktifkan Kirim ke Cabang di POS** di Cabang → Edit sudah dinyalakan.
- **"belum punya Kas Tunai aktif"** → buat Kas Tunai di menu Kas untuk cabang yang disebut.
- **"tidak memotong stok apa pun"** → Produk Jual itu belum punya komposisi.
- **Menu Kiriman Cabang tidak ada?** Minta Owner mencentang **Lihat Kiriman Cabang** / **Terima / Tolak Kiriman dari Gudang** di Role & Hak Akses.
MARKDOWN;

    // ===================== Tahap B3 — Retur Cabang =====================

    private function retur(): void
    {
        Panduan::updateOrCreate(['slug' => 'retur-cabang'], [
            'judul'  => 'Retur Cabang (Cabang → Gudang)',
            'konten' => self::PANDUAN_RETUR,
            'modul'  => 'stok',
            'urutan' => 47,
            'aktif'  => true,
        ]);

        foreach ([
            ['key' => 'retur_cabang.uang_kembali', 'title' => 'Uang dikembalikan?',
                'content' => 'Ya = kas cabang mendapat uang kembali (kas yang dulu membayar kiriman), rugi ditanggung Gudang. Tidak = barang dikembalikan saja, rugi ditanggung cabang (barang masuk ke Gudang bernilai Rp0).'],
            ['key' => 'retur_cabang.koreksi', 'title' => 'Koreksi penjualan',
                'content' => 'Retur dengan uang kembali membuat 1 order koreksi bernilai minus di Gudang, supaya pendapatan & keuntungan Gudang ikut turun. Dibuat sistem — tidak bisa dibatalkan, diedit, atau dihapus.'],
        ] as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'], 'modul' => 'stok', 'aktif' => true, 'urutan' => 100 + $i,
            ]);
        }
    }

    public const PANDUAN_RETUR = <<<'MARKDOWN'
## Tentang
Mengembalikan barang dari **kiriman Gudang yang sudah diterima** — misalnya barang **basi**, **rusak**, atau **kelebihan kirim**. Setiap retur memilih: **uang dikembalikan** atau **tidak**.

| | Uang dikembalikan = **Ya** | Uang dikembalikan = **Tidak** |
|---|---|---|
| Kas cabang | bertambah senilai harga kiriman (ke kas yang dulu membayar) | tetap |
| Kas tunai Gudang | berkurang | tetap |
| Pendapatan & keuntungan Gudang | turun (ada order koreksi bernilai minus) | tetap |
| Nilai stok yang masuk ke Gudang | modal asli Gudang | Rp0 |
| Yang menanggung rugi | Gudang | cabang |

## Cara Pakai

### 1. Kirim retur (di cabang)
1. **Kiriman Cabang → Barang Masuk → Diterima** → buka kirimannya → **Buat Retur**.
2. Isi **Jumlah retur** per barang (maksimal yang diterima dan belum diretur).
3. Pilih **Alasan** dan **Uang dikembalikan?**, isi catatan kalau perlu → **Kirim Retur**.
4. Stok cabang langsung berkurang. Status: *Menunggu*.

### 2. Terima retur (di Gudang)
1. Menu **Stok & Gudang → Retur Cabang → Retur Masuk** (angka merah = menunggu) → **Cek & Terima**.
2. Cek barangnya → **Terima Retur**. Stok masuk ke Gudang, dan kalau uang dikembalikan, kas tunai Gudang berkurang dan kas cabang bertambah.
3. Barang **basi/rusak** lalu dibuang lewat **Stok Barang → Penyesuaian Stok** di Gudang. Barang yang masih bagus (kelebihan kirim) tetap di stok dan bisa dikirim lagi.

### 3. Tolak / batalkan
- Gudang bisa **Tolak Retur** (isi alasan) → barang kembali ke stok cabang.
- Cabang bisa **Batalkan Retur** selama belum diterima → barang kembali ke stok cabang.

## Laporan
- Retur dengan uang kembali tercatat sebagai **order koreksi** bernilai minus di Gudang pada tanggal retur diterima (nama pelanggan "Retur RTR-…"). Laporan Transaksi, Produk, Kategori, dan Tutup Buku Gudang ikut turun.
- Di **Semua Cabang** / Tutup Buku Keseluruhan, retur ikut tidak dihitung bersama kirimannya.
- Uang retur tercatat sebagai pemasukan Penjualan **minus** di Gudang dan pengeluaran Pembelian Bahan **minus** di cabang, supaya laporan keuangan tetap benar.
- Retur **tanpa** uang kembali: cabang kehilangan stok senilai modalnya (tercatat di kartu stok sebagai retur), sama seperti barang yang dibuang.

## Peringatan
- Order koreksi retur dibuat sistem — **tidak bisa dibatalkan, diedit, atau dihapus**.
- Retur yang sudah diterima tidak bisa dibatalkan. Kalau salah, buat kiriman baru dari Gudang.
- Uang kembali butuh **Kas Tunai aktif** di Gudang dan kas aktif di cabang.

## Troubleshooting
- **Tombol Buat Retur tidak ada?** Kiriman belum diterima, Anda bukan cabang penerimanya, atau belum punya hak **Buat / Batalkan Retur ke Gudang**.
- **"maksimal …"** → jumlah retur melebihi barang yang diterima dikurangi retur sebelumnya.
MARKDOWN;

    // ===================== Tahap D — Label Produk gambar =====================

    private function labelGambar(): void
    {
        $p = Panduan::where('slug', 'label-produk')->first();
        if ($p) {
            $konten = (string) $p->konten;
            // Kalimat lama: bahasa bawaan ESC/POS. Coffeeshop membuktikan Blueprint ECO80BT mencetak TSPL.
            $konten = str_replace(
                '- Di Chrome/PC pilih bahasa printer: **ESC/POS** (mis. Blueprint ECO80BT) atau **TSPL** (printer label khusus). Kalau hasil cetak berupa huruf acak, ganti pilihannya.',
                self::KALIMAT_BAHASA_PRINTER,
                $konten
            );
            if (! str_contains($konten, '<!-- LABEL-GAMBAR-449 -->')) {
                $konten = rtrim($konten) . "\n\n" . self::BAGIAN_LABEL_GAMBAR;
            }
            if ($konten !== (string) $p->konten) {
                $p->update(['konten' => $konten]);
            }
        }

        foreach ([
            ['key' => 'label_cup.posisi', 'title' => 'Atur posisi label', 'modul' => 'penjualan',
                'content' => 'Kalau tulisan terpotong atau tidak pas di stiker: cetak label uji, lalu geser kiri/kanan atau atas/bawah dalam mm sampai bingkainya pas di tepi stiker. Angka di sini ditambahkan ke geser cabang dan hanya berlaku di perangkat ini.'],
            ['key' => 'cabang.label_cup_geser', 'title' => 'Geser posisi label', 'modul' => 'cabang',
                'content' => 'Printer & gulungan stiker bisa menggeser isi label beberapa mm. Kiri terlalu lebar dan kanan terpotong → isi minus (mis. -3,5). Kanan terlalu lebar → plus. Berlaku untuk semua tablet/HP cabang ini.'],
        ] as $i => $t) {
            Tooltip::updateOrCreate(['key' => $t['key']], [
                'title' => $t['title'], 'content' => $t['content'], 'modul' => $t['modul'],
                'aktif' => true, 'urutan' => 70 + $i,
            ]);
        }
    }

    public const KALIMAT_BAHASA_PRINTER = '- Di Chrome/PC pilih bahasa printer: **TSPL** (printer label, mis. Blueprint ECO80BT — disarankan) atau **ESC/POS** (printer struk). Kalau hasil cetak berupa huruf acak, ganti pilihannya.';

    public const BAGIAN_LABEL_GAMBAR = <<<'MARKDOWN'
<!-- LABEL-GAMBAR-449 -->
## Cara cetak: Gambar atau Teks printer
Di jendela Label Produk buka **⚙️ Atur posisi label (perangkat ini)** → **Cara cetak**:
- **Gambar** (bawaan, disarankan) — label digambar oleh aplikasi lalu dikirim ke printer sebagai gambar, jadi ukuran huruf & posisi pasti sama di semua printer, dan **contoh di layar = hasil cetak**. Isinya: nama usaha di atas, nomor pesanan besar + urutan stiker (1/3) di kanan, nama produk tebal, varian & catatan, **Exp** (Frozen), nama pelanggan, tipe pesanan + jam + tanggal di bawah.
- **Teks printer** — memakai huruf bawaan printer (ukuran huruf tergantung merek printer). Pakai kalau printer tidak bisa mencetak gambar.
- Kalau dengan cara Gambar stiker tercetak **hitam semua**, centang **Balik hitam-putih**.
- Cara Gambar dan Label Uji hanya untuk printer label **TSPL**. Printer struk (ESC/POS) tetap mencetak teks.

## Label terpotong / tidak pas di stiker
Setiap printer & gulungan stiker posisinya sedikit berbeda.

**Cara paling mudah (sekali untuk semua tablet/HP cabang):** buka **Cabang → Edit → Label Produk**, isi **Geser kiri/kanan (mm)** — kiri terlalu lebar & kanan terpotong → angka minus (mis. **−3,5**), kanan terlalu lebar → plus. **Geser atas/bawah**: plus = ke bawah. Simpan, lalu cetak lagi.

Kalau satu perangkat masih sedikit meleset, di jendela Label Produk buka **⚙️ Atur posisi label (perangkat ini)** — angkanya **ditambahkan** ke setelan cabang:
1. Klik **🧪 Cetak Label Uji** — tercetak bingkai tebal yang seharusnya pas di tepi stiker.
2. Terpotong di **kiri** → isi geser kiri/kanan dengan angka plus; terpotong di **kanan** → angka minus.
3. Tulisan terbalik → centang/hapus centang **Putar tulisan 180°**.
4. Cetak uji lagi sampai pas. Pengaturan ini disimpan di perangkat itu saja.

Pastikan juga **ukuran stiker** di Cabang → Edit sama dengan stiker yang terpasang (mis. 40 × 30 mm).

**Catatan pesanan** (kolom Catatan di POS) ikut tercetak di setiap stiker pesanan itu, di bawah catatan item.
MARKDOWN;

    // ===================== Tahap C — POS tampilan HP =====================

    private function posHp(): void
    {
        $p = Panduan::where('slug', 'pos')->first();
        if ($p && ! str_contains((string) $p->konten, '<!-- POS-HP-449 -->')) {
            $p->update(['konten' => rtrim((string) $p->konten) . "\n\n" . self::BAGIAN_POS_HP]);
        }
    }

    public const BAGIAN_POS_HP = <<<'MARKDOWN'
<!-- POS-HP-449 -->
## Memakai POS di HP
Di layar HP, POS otomatis memakai tampilan ringkas (tablet & PC tidak berubah):
- Produk tampil **3 kolom** dengan gambar kecil; deretan kategori bisa digeser ke samping.
- Kartu **Pelanggan** dilipat — ketuk judulnya kalau perlu mengisi nama/telepon.
- **Bilah di bawah layar** selalu menampilkan jumlah item & total. Ketuk **Keranjang** untuk melihat/mengubah item, **Bayar ▸** untuk langsung ke bagian pembayaran.
- Tombol Mode Desktop/Tablet disembunyikan di HP (tidak diperlukan).
- Cetak struk & label tetap lewat aplikasi kasir Android atau Bluetooth seperti di tablet. Label terpotong? Lihat panduan **Label Produk → Label terpotong / tidak pas di stiker**.
MARKDOWN;
}
