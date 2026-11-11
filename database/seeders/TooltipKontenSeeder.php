<?php

namespace Database\Seeders;

use App\Models\Tooltip;
use Illuminate\Database\Seeder;

class TooltipKontenSeeder extends Seeder
{
    public function run(): void
    {
        $tooltips = [

            // ── ITEM (Master Barang) ──────────────────────────────────────────
            [
                'key'     => 'item.kode_item',
                'title'   => 'Kode Item',
                'content' => 'Kode unik untuk item ini (contoh: BB-001). Otomatis uppercase. Tidak boleh sama dengan item lain. Dipakai di barcode, laporan stok, dan PO.',
                'modul'   => 'item',
                'urutan'  => 1,
            ],
            [
                'key'     => 'item.tipe',
                'title'   => 'Tipe Item',
                'content' => 'Menentukan di mana item muncul: Bahan Baku = stok produksi; Produk Jadi = tampil di POS untuk dijual; Kemasan = plastik/kardus; Lainnya = item di luar 3 kategori di atas.',
                'modul'   => 'item',
                'urutan'  => 2,
            ],
            [
                'key'     => 'item.item_category_id',
                'title'   => 'Kategori Item',
                'content' => 'Kategori untuk pengelompokan di laporan stok dan filter. Contoh: Daging Segar, Bumbu, Kemasan Plastik. Bisa tambah kategori baru langsung di form ini.',
                'modul'   => 'item',
                'urutan'  => 3,
            ],
            [
                'key'     => 'item.harga_beli_terakhir',
                'title'   => 'Harga Beli Terakhir',
                'content' => 'Snapshot harga beli terakhir dari vendor. Digunakan sebagai harga default saat membuat PO baru dan sebagai acuan biaya variabel di kalkulasi BEP. Otomatis diperbarui setiap PO diterima.',
                'modul'   => 'item',
                'urutan'  => 4,
            ],
            [
                'key'     => 'item.harga_jual',
                'title'   => 'Harga Jual',
                'content' => 'Harga jual default yang muncul di POS saat item ini dipilih. Bisa di-override per transaksi di POS. Digunakan juga sebagai acuan harga jual di kalkulasi BEP.',
                'modul'   => 'item',
                'urutan'  => 5,
            ],
            [
                'key'     => 'item.qty_minimum',
                'title'   => 'Qty Minimum (Stok Alert)',
                'content' => 'Jika stok di suatu lokasi turun di bawah nilai ini, sistem otomatis kirim notifikasi peringatan ke Manajer Cabang dan Admin Gudang. Isi 0 untuk nonaktifkan alert.',
                'modul'   => 'item',
                'urutan'  => 6,
            ],

            // ── PEMBELIAN (Purchase Order) ────────────────────────────────────
            [
                'key'     => 'pembelian.supplier_id',
                'title'   => 'Supplier',
                'content' => 'Pilih pemasok/vendor tempat membeli bahan baku. Data supplier dikelola di menu Master › Supplier. Kode supplier tercatat di laporan pembelian untuk rekonsiliasi.',
                'modul'   => 'pembelian',
                'urutan'  => 1,
            ],
            [
                'key'     => 'pembelian.cabang_id',
                'title'   => 'Lokasi Tujuan',
                'content' => 'Stok dari PO ini akan masuk ke lokasi yang dipilih. Untuk alur normal: pilih Gudang Pusat. Untuk pembelian langsung/mendesak: pilih cabang yang butuh bahan.',
                'modul'   => 'pembelian',
                'urutan'  => 2,
            ],
            [
                'key'     => 'pembelian.pembelian_langsung',
                'title'   => 'Pembelian Langsung / Mendesak',
                'content' => 'Centang jika cabang membeli langsung ke vendor tanpa melalui gudang pusat. PO ini akan ditandai "mendesak", butuh approval Manajer/Owner, dan tercatat di laporan pembelian di luar jalur normal.',
                'modul'   => 'pembelian',
                'urutan'  => 3,
            ],
            [
                'key'     => 'pembelian.alasan_langsung',
                'title'   => 'Alasan Mendesak',
                'content' => 'Wajib diisi jika ini adalah pembelian langsung. Jelaskan kenapa harus beli langsung (misal: stok habis mendadak, gudang pusat tidak punya stok). Muncul di laporan untuk evaluasi manajemen.',
                'modul'   => 'pembelian',
                'urutan'  => 4,
            ],
            [
                'key'     => 'pembelian.harga_satuan',
                'title'   => 'Harga Satuan',
                'content' => 'Harga beli per unit dari vendor. Nilai ini direkam sebagai batch FIFO baru saat PO diterima. HPP (Harga Pokok Penjualan) produk dihitung dari urutan batch FIFO tertua → termuda.',
                'modul'   => 'pembelian',
                'urutan'  => 5,
            ],

            // ── STOK REQUEST ──────────────────────────────────────────────────
            [
                'key'     => 'stock-request.qty_diminta',
                'title'   => 'Qty Diminta',
                'content' => 'Jumlah yang diminta dari Gudang Pusat. Gudang Pusat bisa menyetujui jumlah yang berbeda (Qty Disetujui). Stok cabang bertambah sesuai Qty Disetujui, bukan Qty Diminta.',
                'modul'   => 'stok',
                'urutan'  => 1,
            ],

            // ── STOCK TRANSFER ────────────────────────────────────────────────
            [
                'key'     => 'stock-transfer.dari_lokasi_id',
                'title'   => 'Dari Lokasi',
                'content' => 'Stok dikurangi dari lokasi ini saat status transfer berubah ke "Dikirim". Pastikan ada stok cukup di lokasi asal sebelum mengirim.',
                'modul'   => 'stok',
                'urutan'  => 2,
            ],
            [
                'key'     => 'stock-transfer.ke_lokasi_id',
                'title'   => 'Ke Lokasi',
                'content' => 'Stok ditambahkan ke lokasi ini saat status transfer berubah ke "Diterima". Saat masih "Dikirim", stok tujuan belum bertambah.',
                'modul'   => 'stok',
                'urutan'  => 3,
            ],
            [
                'key'     => 'stock-transfer.stock_request_id',
                'title'   => 'Berdasarkan Permintaan',
                'content' => 'Kaitkan transfer ini dengan Stock Request tertentu dari cabang. Opsional — transfer bisa dibuat tanpa permintaan (pengiriman inisiatif gudang). Jika dikaitkan, status request akan diperbarui otomatis.',
                'modul'   => 'stok',
                'urutan'  => 4,
            ],

            // ── KARYAWAN ──────────────────────────────────────────────────────
            [
                'key'     => 'karyawan.nik',
                'title'   => 'NIK (Nomor Induk Karyawan)',
                'content' => 'Kode identifikasi unik karyawan, format KRY-001. Dikosongkan = sistem auto-generate. Digunakan di slip gaji, absensi, dan laporan HR.',
                'modul'   => 'karyawan',
                'urutan'  => 1,
            ],
            [
                'key'     => 'karyawan.tipe_karyawan',
                'title'   => 'Tipe Karyawan',
                'content' => 'Tetap = karyawan permanen; Kontrak = masa kerja terbatas; Harian = dibayar per hari. Mempengaruhi kebijakan cuti, perhitungan gaji, dan hak karyawan di sistem.',
                'modul'   => 'karyawan',
                'urutan'  => 2,
            ],
            [
                'key'     => 'karyawan.atasan_id',
                'title'   => 'Atasan Langsung',
                'content' => 'Atasan yang bertanggung jawab menilai karyawan ini dalam sistem evaluasi 360°. Atasan otomatis menjadi reviewer dengan bobot penilaian 50%. Bisa dikosongi jika tidak ada atasan.',
                'modul'   => 'karyawan',
                'urutan'  => 3,
            ],
            [
                'key'     => 'karyawan.gaji_pokok',
                'title'   => 'Gaji Pokok',
                'content' => 'Gaji pokok bulanan — menjadi dasar perhitungan tunjangan BPJS, tarif lembur per jam (gaji ÷ 173 × 1,5), dan proporsional hari hadir di slip gaji otomatis.',
                'modul'   => 'karyawan',
                'urutan'  => 4,
            ],
            [
                'key'     => 'karyawan.bpjs_kesehatan_persen',
                'title'   => 'BPJS Kesehatan (%)',
                'content' => 'Persentase potongan BPJS Kesehatan yang ditanggung karyawan dari gaji pokok. Default 1% sesuai regulasi. Nilai ini digunakan sebagai default di slip gaji, bisa di-override per slip.',
                'modul'   => 'karyawan',
                'urutan'  => 5,
            ],
            [
                'key'     => 'karyawan.bpjs_tk_persen',
                'title'   => 'BPJS JHT (%)',
                'content' => 'Persentase potongan BPJS Ketenagakerjaan (Jaminan Hari Tua) yang ditanggung karyawan dari gaji pokok. Default 2% sesuai regulasi. Nilai ini digunakan sebagai default di slip gaji.',
                'modul'   => 'karyawan',
                'urutan'  => 6,
            ],
            [
                'key'     => 'karyawan.buat_akun_login',
                'title'   => 'Buat Akun Login',
                'content' => 'Jika dicentang, karyawan bisa login ke sistem untuk mengakses absensi mandiri, melihat slip gaji, dan mengisi form penilaian 360°. Role menentukan menu apa yang bisa diakses.',
                'modul'   => 'karyawan',
                'urutan'  => 7,
            ],
            [
                'key'     => 'karyawan.user_role',
                'title'   => 'Role Akun',
                'content' => 'Role menentukan hak akses: Kasir = POS & penjualan; Operator = stok & produksi; Manajer = kelola 1 cabang; Admin Gudang = gudang pusat. Owner tidak bisa di-assign ke karyawan biasa.',
                'modul'   => 'karyawan',
                'urutan'  => 8,
            ],

            // ── ASET ──────────────────────────────────────────────────────────
            [
                'key'     => 'aset.kode_aset',
                'title'   => 'Kode Aset',
                'content' => 'Kode unik aset (auto-generate dari sistem, format AST-001). Digunakan di label fisik aset, laporan penyusutan, dan surat perintah mutasi. Bisa diubah tapi harus tetap unik.',
                'modul'   => 'aset',
                'urutan'  => 1,
            ],
            [
                'key'     => 'aset.kategori_aset_id',
                'title'   => 'Kategori Aset',
                'content' => 'Pengelompokan aset: Mesin Produksi, Kendaraan, Peralatan Dapur, Elektronik, Furniture, Bangunan. Digunakan untuk laporan nilai aset per kategori dan penentuan kebijakan penyusutan.',
                'modul'   => 'aset',
                'urutan'  => 2,
            ],
            [
                'key'     => 'aset.lokasi_id',
                'title'   => 'Lokasi Aset',
                'content' => 'Cabang atau gudang pusat tempat aset saat ini berada. Berubah otomatis saat aset dimutasi ke lokasi lain. Penyusutan dihitung per aset, bukan per lokasi.',
                'modul'   => 'aset',
                'urutan'  => 3,
            ],
            [
                'key'     => 'aset.harga_perolehan',
                'title'   => 'Harga Perolehan',
                'content' => 'Harga beli/nilai aset saat pertama kali diperoleh. Menjadi dasar perhitungan penyusutan semua metode. Dicatat juga sebagai beban aset di laporan keuangan.',
                'modul'   => 'aset',
                'urutan'  => 4,
            ],
            [
                'key'     => 'aset.nilai_residu',
                'title'   => 'Nilai Residu',
                'content' => 'Estimasi nilai aset di akhir umur ekonomisnya (nilai sisa setelah habis disusutkan). Digunakan dalam formula: Penyusutan = (Harga - Residu) ÷ Umur Ekonomis. Isi 0 jika tidak ada nilai sisa.',
                'modul'   => 'aset',
                'urutan'  => 5,
            ],
            [
                'key'     => 'aset.kondisi',
                'title'   => 'Kondisi Aset',
                'content' => 'Kondisi fisik aset saat ini: Baik, Rusak Ringan, Rusak Berat, atau Dihapuskan. Digunakan untuk monitoring kebutuhan maintenance dan keputusan disposal aset.',
                'modul'   => 'aset',
                'urutan'  => 6,
            ],
            [
                'key'     => 'aset.metode_penyusutan',
                'title'   => 'Metode Penyusutan',
                'content' => 'Garis Lurus: susut sama setiap bulan (cocok: bangunan, furniture). Saldo Menurun: susut besar di awal (cocok: kendaraan, elektronik). Satuan Produksi: susut per kg output (cocok: mesin giling).',
                'modul'   => 'aset',
                'urutan'  => 7,
            ],
            [
                'key'     => 'aset.umur_ekonomis_bulan',
                'title'   => 'Umur Ekonomis (Bulan)',
                'content' => 'Berapa bulan aset diperkirakan masih bermanfaat secara ekonomis. Contoh: 60 bulan = 5 tahun. Menentukan jumlah periode penyusutan untuk metode Garis Lurus.',
                'modul'   => 'aset',
                'urutan'  => 8,
            ],
            [
                'key'     => 'aset.tarif_penyusutan',
                'title'   => 'Tarif Penyusutan (%/bulan)',
                'content' => 'Khusus metode Saldo Menurun: persentase dari nilai buku yang disusutkan per bulan. Contoh: 2%/bulan = nilai buku dikurangi 2% setiap bulan (makin kecil tiap bulan karena dihitung dari nilai buku yang terus turun).',
                'modul'   => 'aset',
                'urutan'  => 9,
            ],
            [
                'key'     => 'aset.estimasi_produksi_total',
                'title'   => 'Estimasi Total Produksi',
                'content' => 'Khusus metode Satuan Produksi: estimasi total unit/kg yang bisa diproses selama umur ekonomis aset. Penyusutan bulan ini = (Harga - Residu) ÷ Total Estimasi × Produksi Aktual Bulan Ini.',
                'modul'   => 'aset',
                'urutan'  => 10,
            ],

            // ── BEP (Break Even Point) ────────────────────────────────────────
            [
                'key'     => 'bep.periode',
                'title'   => 'Periode BEP',
                'content' => 'Bulan dan tahun yang dianalisis (format YYYY-MM). BEP dihitung ulang setiap bulan karena biaya tetap dan variabel bisa berubah. Satu setting BEP per cabang per bulan.',
                'modul'   => 'bep',
                'urutan'  => 1,
            ],
            [
                'key'     => 'bep.nama_komponen',
                'title'   => 'Nama Komponen Biaya Tetap',
                'content' => 'Nama pengeluaran tetap yang tidak berubah meskipun produksi naik/turun. Contoh: Gaji Kasir Rp 1.5jt, Sewa Tempat Rp 2jt, Listrik Minimum Rp 500rb.',
                'modul'   => 'bep',
                'urutan'  => 2,
            ],
            [
                'key'     => 'bep.kategori_biaya_tetap',
                'title'   => 'Kategori Biaya Tetap',
                'content' => 'Pengelompokan biaya tetap: Gaji, Sewa, Depresiasi (penyusutan aset), Listrik, Asuransi, Lainnya. Digunakan untuk laporan breakdown biaya tetap per kategori.',
                'modul'   => 'bep',
                'urutan'  => 3,
            ],
            [
                'key'     => 'bep.tipe_produk',
                'title'   => 'Tipe (Produk / Jasa Giling)',
                'content' => 'Produk = bakso, sosis, tempura (Berkah Mulyo produksi & jual sendiri). Jasa Giling = pelanggan bawa daging sendiri untuk digiling (tarif per kg). BEP dihitung terpisah per tipe.',
                'modul'   => 'bep',
                'urutan'  => 4,
            ],
            [
                'key'     => 'bep.harga_jual_per_unit',
                'title'   => 'Harga Jual Per Unit',
                'content' => 'Harga jual per kg atau per unit untuk produk/jasa ini. Formula Margin Kontribusi = Harga Jual - Biaya Variabel. BEP Unit = Biaya Tetap ÷ Margin Kontribusi.',
                'modul'   => 'bep',
                'urutan'  => 5,
            ],
            [
                'key'     => 'bep.biaya_variabel_per_unit',
                'title'   => 'Biaya Variabel Per Unit',
                'content' => 'Biaya yang langsung berubah sesuai volume produksi per kg/unit: bahan baku, kemasan, gas/listrik produksi. Semakin tinggi biaya variabel, semakin tinggi BEP yang harus dicapai.',
                'modul'   => 'bep',
                'urutan'  => 6,
            ],
            [
                'key'     => 'bep.target_penjualan_unit',
                'title'   => 'Target Penjualan (Unit)',
                'content' => 'Target penjualan unit per bulan untuk produk ini. Dibandingkan dengan BEP Unit untuk monitoring: jika target > BEP = akan untung, jika target < BEP = akan rugi. Diisi otomatis dari data penjualan aktual bulan sebelumnya.',
                'modul'   => 'bep',
                'urutan'  => 7,
            ],

            // ── KEUANGAN (Transaksi) ──────────────────────────────────────────
            [
                'key'     => 'keuangan.tipe',
                'title'   => 'Tipe Transaksi',
                'content' => 'Pemasukan = uang masuk ke kas (penjualan, piutang cair, dll). Pengeluaran = uang keluar dari kas (pembelian, gaji, sewa, dll). Pilihan ini menentukan kategori apa yang tersedia di bawah.',
                'modul'   => 'keuangan',
                'urutan'  => 1,
            ],
            [
                'key'     => 'keuangan.kategori_id',
                'title'   => 'Kategori Transaksi',
                'content' => 'Kategori menentukan akun di laporan Laba Rugi. Contoh: Pemasukan › Penjualan Produk, Pengeluaran › Bahan Baku, Pengeluaran › Gaji. Penting untuk analisis breakdown pendapatan & pengeluaran.',
                'modul'   => 'keuangan',
                'urutan'  => 2,
            ],
            [
                'key'     => 'keuangan.kas_id',
                'title'   => 'Kas',
                'content' => 'Pilih kas yang terpengaruh oleh transaksi ini. Saldo kas akan otomatis bertambah (pemasukan) atau berkurang (pengeluaran). Kosongkan jika transaksi ini hanya pencatatan akuntansi tanpa aliran kas nyata.',
                'modul'   => 'keuangan',
                'urutan'  => 3,
            ],
            [
                'key'     => 'keuangan.bukti',
                'title'   => 'Bukti Transaksi',
                'content' => 'Upload foto struk, nota, atau scan bukti pembayaran. Penting untuk audit trail dan rekonsiliasi keuangan. Format: JPG, PNG, atau PDF. Maksimal 5MB.',
                'modul'   => 'keuangan',
                'urutan'  => 4,
            ],

            // ── RECURRING (Transaksi Berulang) ────────────────────────────────
            [
                'key'     => 'recurring.tipe',
                'title'   => 'Tipe (Pemasukan/Pengeluaran)',
                'content' => 'Menentukan apakah template ini untuk pemasukan atau pengeluaran. Mempengaruhi kategori yang tersedia dan arah aliran kas saat transaksi di-generate.',
                'modul'   => 'keuangan',
                'urutan'  => 5,
            ],
            [
                'key'     => 'recurring.frekuensi',
                'title'   => 'Frekuensi',
                'content' => 'Bulanan = transaksi di-generate 1x per bulan pada tanggal jatuh tempo. Mingguan = di-generate setiap minggu pada hari yang sama. Sistem generate otomatis via Laravel Scheduler.',
                'modul'   => 'keuangan',
                'urutan'  => 6,
            ],
            [
                'key'     => 'recurring.tanggal_jatuh_tempo',
                'title'   => 'Tanggal Jatuh Tempo',
                'content' => 'Hari dalam bulan (1–28) saat transaksi ini otomatis di-generate. Contoh: isi 1 = transaksi dibuat setiap tanggal 1. Dibatasi sampai 28 agar aman untuk semua bulan (termasuk Februari).',
                'modul'   => 'keuangan',
                'urutan'  => 7,
            ],
            [
                'key'     => 'recurring.auto_approve',
                'title'   => 'Auto-Approve',
                'content' => 'Jika aktif, transaksi yang di-generate otomatis langsung berstatus "Disetujui" tanpa perlu review manual. Nonaktifkan untuk transaksi yang nominal-nya bisa berubah setiap periode (perlu dicek dulu).',
                'modul'   => 'keuangan',
                'urutan'  => 8,
            ],
            [
                'key'     => 'recurring.kas_id',
                'title'   => 'Kas Tujuan',
                'content' => 'Kas yang akan digunakan saat transaksi berulang ini di-generate. Saldo kas ini akan otomatis berkurang/bertambah setiap periode. Pilih kas yang sesuai dengan metode pembayaran transaksi ini.',
                'modul'   => 'keuangan',
                'urutan'  => 9,
            ],

            // ── SHIFT ─────────────────────────────────────────────────────────
            [
                'key'     => 'shift.toleransi_telat_menit',
                'title'   => 'Toleransi Telat (Menit)',
                'content' => 'Batas waktu toleransi keterlambatan. Karyawan yang clock-in setelah jam masuk + toleransi ini dianggap terlambat dan `menit_terlambat` dicatat. Nilai ini mempengaruhi potongan gaji jika ada pengaturan potongan telat.',
                'modul'   => 'absensi',
                'urutan'  => 1,
            ],

            // ── HARI LIBUR ────────────────────────────────────────────────────
            [
                'key'     => 'hari-libur.tipe',
                'title'   => 'Tipe Hari Libur',
                'content' => 'Nasional = berlaku untuk semua cabang (Hari Raya, Kemerdekaan, dll). Khusus Cabang = hanya berlaku di satu cabang tertentu (libur lokal, event khusus). Karyawan yang absen di hari libur tidak dihitung alpha.',
                'modul'   => 'absensi',
                'urutan'  => 2,
            ],

            // ── CUTI ─────────────────────────────────────────────────────────
            [
                'key'     => 'cuti.tipe',
                'title'   => 'Tipe Cuti',
                'content' => 'Cuti Tahunan = hak cuti reguler (biasanya 12 hari/tahun). Izin = tidak hadir dengan alasan valid (dokter, keluarga). Sakit = dengan surat dokter. Cuti Khusus = pernikahan, duka, dll. Mempengaruhi catatan di rekap absensi.',
                'modul'   => 'absensi',
                'urutan'  => 3,
            ],

            // ── EVALUASI 360° ─────────────────────────────────────────────────
            [
                'key'     => 'evaluasi.nama_periode',
                'title'   => 'Nama Periode Penilaian',
                'content' => 'Label periode penilaian 360°, biasanya format "Q1 2026" (Januari–Maret) atau "Triwulan 2 2026" (April–Juni). Digunakan sebagai judul di laporan dan notifikasi ke karyawan.',
                'modul'   => 'evaluasi',
                'urutan'  => 1,
            ],
            [
                'key'     => 'evaluasi.deadline_pengisian',
                'title'   => 'Deadline Pengisian',
                'content' => 'Batas waktu semua penilai (atasan, rekan kerja, self-assessment) harus selesai mengisi form penilaian. Sistem kirim reminder otomatis 3 hari sebelum deadline. Setelah deadline, skor dihitung otomatis.',
                'modul'   => 'evaluasi',
                'urutan'  => 2,
            ],

            // ── PENGGAJIAN (Slip Gaji) ────────────────────────────────────────
            [
                'key'     => 'penggajian.uang_lembur',
                'title'   => 'Uang Lembur',
                'content' => 'Dihitung otomatis: Total Jam Lembur × Tarif Lembur/Jam (dari Pengaturan Penggajian). Edit manual jika tarif berbeda — badge akan berubah jadi "Manual Override". Klik ↺ untuk reset ke nilai otomatis.',
                'modul'   => 'penggajian',
                'urutan'  => 1,
            ],
            [
                'key'     => 'penggajian.potongan_absensi',
                'title'   => 'Potongan Alpha / Absensi',
                'content' => 'Dihitung otomatis: Jumlah Hari Alpha × Tarif Potongan Alpha/Hari (dari Pengaturan Penggajian). Edit manual jika perlu penyesuaian. Klik ↺ untuk reset ke nilai otomatis.',
                'modul'   => 'penggajian',
                'urutan'  => 2,
            ],
            [
                'key'     => 'penggajian.pph21',
                'title'   => 'PPh21 (Pajak Penghasilan)',
                'content' => 'Pajak penghasilan karyawan, dihitung dari total penghasilan bruto setahun. PTKP (Penghasilan Tidak Kena Pajak) TK/0 = Rp 54 juta. Tarif progresif: 5% (s.d. Rp 60jt), 15% (Rp 60–250jt), 25% (Rp 250–500jt), 30% (di atas Rp 500jt).',
                'modul'   => 'penggajian',
                'urutan'  => 3,
            ],
            [
                'key'     => 'penggajian.bpjs_kesehatan',
                'title'   => 'BPJS Kesehatan (Karyawan)',
                'content' => 'Potongan BPJS Kesehatan yang ditanggung karyawan — default 1% dari gaji pokok (sesuai data karyawan). Ubah jika tarif berbeda untuk bulan ini. Nilai ini tidak termasuk porsi yang dibayar perusahaan.',
                'modul'   => 'penggajian',
                'urutan'  => 4,
            ],
            [
                'key'     => 'penggajian.bpjs_ketenagakerjaan',
                'title'   => 'BPJS JHT (Karyawan)',
                'content' => 'Potongan Jaminan Hari Tua (JHT) yang ditanggung karyawan — default 2% dari gaji pokok. Ubah jika tarif berbeda. Nilai ini tidak termasuk porsi JHT yang dibayar perusahaan (3,7%).',
                'modul'   => 'penggajian',
                'urutan'  => 5,
            ],
            [
                'key'     => 'penggajian.tunjangan_kehadiran',
                'title'   => 'Premi Kehadiran',
                'content' => 'Tunjangan kehadiran yang diberikan jika karyawan hadir ≥80% dari hari kerja bulan ini. Isi 0 jika tidak ada premi kehadiran. Dihitung per bulan, bukan per hari.',
                'modul'   => 'penggajian',
                'urutan'  => 6,
            ],
            [
                'key'     => 'penggajian.insentif',
                'title'   => 'Insentif / Komisi Penjualan',
                'content' => 'Komisi atau insentif berdasarkan pencapaian penjualan bulan ini. Isian manual — tidak ada kalkulasi otomatis. Cocok untuk kasir atau sales yang mendapat komisi berdasarkan target omzet.',
                'modul'   => 'penggajian',
                'urutan'  => 7,
            ],

            // ── PENGATURAN PENGGAJIAN ─────────────────────────────────────────
            [
                'key'     => 'pengaturan-gaji.tarif_lembur_per_jam',
                'title'   => 'Tarif Lembur Per Jam',
                'content' => 'Tarif upah lembur global (Rp/jam) yang dipakai otomatis di semua slip gaji. Default Rp 15.000/jam. Bisa di-override per slip gaji individual jika diperlukan. Sistem juga bisa hitung otomatis: Gaji Pokok ÷ 173 × 1,5.',
                'modul'   => 'penggajian',
                'urutan'  => 8,
            ],
            [
                'key'     => 'pengaturan-gaji.potongan_alpa_per_hari',
                'title'   => 'Potongan Alpha Per Hari',
                'content' => 'Potongan gaji per hari tidak hadir tanpa keterangan (alpha). Isi 0 = potongan dihitung proporsional (Gaji Pokok ÷ Hari Kerja Sebulan × Hari Alpha). Isi nilai = potongan tetap per hari alpha.',
                'modul'   => 'penggajian',
                'urutan'  => 9,
            ],
            [
                'key'     => 'pengaturan-gaji.potongan_telat_per_menit',
                'title'   => 'Potongan Telat Per Menit',
                'content' => 'Potongan gaji per menit keterlambatan masuk kerja. Data menit terlambat diambil dari rekap absensi (clock-in vs jam masuk shift + toleransi). Isi 0 untuk menonaktifkan potongan keterlambatan.',
                'modul'   => 'penggajian',
                'urutan'  => 10,
            ],
            [
                'key'     => 'pengaturan-gaji.hari_kerja_per_minggu',
                'title'   => 'Hari Kerja Per Minggu',
                'content' => 'Referensi hari kerja per minggu untuk menghitung total hari kerja efektif sebulan. Default 6 hari (Senin–Sabtu). Dipakai untuk perhitungan gaji proporsional dan potongan alpha proporsional.',
                'modul'   => 'penggajian',
                'urutan'  => 11,
            ],

            // ── USER ──────────────────────────────────────────────────────────
            [
                'key'     => 'user.role',
                'title'   => 'Role User',
                'content' => 'Menentukan hak akses menu dan fitur. Owner/Admin Pusat = semua cabang; Admin Gudang = gudang pusat; Manajer = 1 cabang penuh; Kasir = POS & penjualan; Operator = stok & produksi. Role ini bisa dikombinasikan dengan cabang yang di-assign.',
                'modul'   => 'user',
                'urutan'  => 1,
            ],
            [
                'key'     => 'user.default_cabang_id',
                'title'   => 'Cabang Default',
                'content' => 'Cabang yang langsung aktif saat user login, jika user di-assign ke lebih dari satu cabang. User bisa pindah cabang kapan saja via switcher di navbar tanpa perlu logout. Wajib dipilih jika user di-assign ke 2+ cabang.',
                'modul'   => 'user',
                'urutan'  => 2,
            ],

            // ── ABSENSI (Edit Manual) ─────────────────────────────────────────
            [
                'key'     => 'absensi.status',
                'title'   => 'Status Kehadiran',
                'content' => 'Hadir = masuk kerja; Izin = tidak hadir dengan izin resmi; Sakit = tidak hadir sakit; Alpha = tidak hadir tanpa keterangan (akan kena potongan gaji); Libur = hari libur nasional/cabang; Cuti = sedang cuti yang disetujui.',
                'modul'   => 'absensi',
                'urutan'  => 4,
            ],
        ];

        $count = 0;
        foreach ($tooltips as $data) {
            Tooltip::firstOrCreate(
                ['key' => $data['key']],
                array_merge($data, ['aktif' => true])
            );
            $count++;
        }

        $this->command->info("TooltipKontenSeeder: {$count} tooltip berhasil di-seed.");

        // ── POS — updateOrCreate agar konten selalu diperbarui saat re-seed ──
        $posTooltips = [
            [
                'key'     => 'pos.cetak_otomatis',
                'title'   => 'Cetak Otomatis',
                'content' => 'Jika dicentang, modal pratinjau struk akan muncul otomatis setelah PROSES ditekan. Dari modal, kasir bisa cetak langsung tanpa membuka tab baru — aman untuk PWA dan tablet.',
                'modul'   => 'pos',
                'urutan'  => 1,
            ],
            [
                'key'     => 'pos.salinan_struk',
                'title'   => 'Salinan Struk',
                'content' => 'Pilih 1× untuk satu salinan, atau 2× untuk dua salinan sekaligus dalam satu job cetak (dipisah garis potong - - -). Berguna jika kasir dan pelanggan masing-masing perlu menyimpan bukti.',
                'modul'   => 'pos',
                'urutan'  => 2,
            ],
            [
                'key'     => 'pos.mode_tampilan',
                'title'   => 'Mode Tampilan POS',
                'content' => 'Desktop: tampilan penuh dengan sidebar & navbar. Tablet: sembunyikan sidebar & navbar, semua elemen diperkecil agar muat di layar 9–10 inci landscape. Mode disimpan otomatis dan aktif saat buka POS berikutnya.',
                'modul'   => 'pos',
                'urutan'  => 3,
            ],
            [
                'key'     => 'cabang.footer_struk',
                'title'   => 'Footer Struk per Cabang',
                'content' => 'Teks khusus yang tampil di bagian bawah struk POS cabang ini, di bawah garis pemisah. Kosongkan untuk pakai teks default sistem. Bisa berisi ucapan terima kasih, nomor WA, atau info promo. Maks. 500 karakter.',
                'modul'   => 'cabang',
                'urutan'  => 1,
            ],
            [
                'key'     => 'cabang.izinkan_sembunyi_harga_struk',
                'title'   => 'Izinkan Sembunyi Harga per Item',
                'content' => 'Kalau dicentang, kasir di cabang ini akan melihat checkbox tambahan di POS untuk mencetak struk tanpa harga per item (cuma nama, qty, dan TOTAL akhir yang tercetak) — berguna untuk hindari komplain pelanggan soal harga bahan tertentu. Default checkbox di POS: tidak tampil harga. Cabang yang tidak dicentang di sini tidak terpengaruh sama sekali (struk tetap tampil harga penuh seperti biasa).',
                'modul'   => 'cabang',
                'urutan'  => 2,
            ],
            [
                'key'     => 'pos.tampil_harga_struk',
                'title'   => 'Tampilkan Harga per Item di Struk',
                'content' => 'Checkbox ini cuma muncul kalau Owner sudah mengizinkan di pengaturan cabang. Default TIDAK dicentang — struk akan cetak nama & qty tiap item tanpa harga satuan/subtotal, cuma TOTAL akhir yang tercetak. Centang kalau pelanggan minta lihat rincian harga. Ini murni preferensi cetak, tidak mengubah data order — Riwayat Order tetap selalu tampil harga lengkap.',
                'modul'   => 'pos',
                'urutan'  => 4,
            ],
        ];

        foreach ($posTooltips as $data) {
            Tooltip::updateOrCreate(
                ['key' => $data['key']],
                array_merge($data, ['aktif' => true])
            );
        }

        // ── Master Jenis Menu (nama tampilan; tooltip key tetap "master.jenis_olahan", zero migration) ──
        Tooltip::updateOrCreate(
            ['key' => 'master.jenis_olahan'],
            [
                'key'     => 'master.jenis_olahan',
                'title'   => 'Jenis Menu',
                'content' => 'Kategorisasi referensi untuk mengelompokkan Master Bumbu Pusat (mis. Dimsum, Gyoza). Tambah jenis baru kapan saja. Nonaktifkan = hilang dari pilihan, tapi data resep yang sudah pakai jenis ini tetap aman. Slug tidak bisa diubah setelah dibuat.',
                'modul'   => 'master',
                'urutan'  => 1,
                'aktif'   => true,
            ]
        );

        // ── Master Varian Produk — LIVE di POS sejak Tahap 3 (2026-09-13),
        //    halaman kelola master-nya sendiri masih menyusul ──
        Tooltip::updateOrCreate(
            ['key' => 'master.item_varian'],
            [
                'key'     => 'master.item_varian',
                'title'   => 'Varian Produk',
                'content' => 'Sudah aktif di POS — produk ber-varian (mis. Size S/M/L) otomatis muncul modal pilih saat kasir klik produknya. Halaman kelola master atribut/varian sendiri masih menyusul, untuk sekarang dikelola lewat Tinker/seeder oleh developer.',
                'modul'   => 'master',
                'urutan'  => 2,
                'aktif'   => true,
            ]
        );

        // ── Tahap 3 D'mentai — Config POS per outlet ──
        $posConfigTooltips = [
            [
                'key'     => 'cabang.dine_in_aktif',
                'title'   => 'Dine-in Aktif',
                'content' => 'Aktifkan/nonaktifkan tipe transaksi Dine-in untuk outlet ini. Kalau nonaktif, tab Dine-in tidak muncul sama sekali di POS outlet ini.',
            ],
            [
                'key'     => 'cabang.nomor_meja_aktif',
                'title'   => 'Input Nomor Meja',
                'content' => 'Kalau aktif, kasir WAJIB isi nomor meja setiap transaksi Dine-in. Cocok dinonaktifkan untuk outlet tanpa sistem nomor meja (mis. konter kecil).',
            ],
            [
                'key'     => 'cabang.take_away_fee',
                'title'   => 'Takeaway Fee',
                'content' => 'Biaya tambahan flat (Rp) yang otomatis ditambahkan ke total setiap transaksi Takeaway di outlet ini. Isi 0 kalau tidak ada biaya tambahan.',
            ],
            [
                'key'     => 'cabang.service_charge_persen',
                'title'   => 'Service Charge (%)',
                'content' => 'Persentase biaya layanan yang otomatis dihitung dari subtotal (setelah diskon) setiap transaksi di outlet ini, apapun tipe transaksinya. Isi 0 kalau tidak ada service charge.',
            ],
        ];

        foreach ($posConfigTooltips as $data) {
            Tooltip::updateOrCreate(['key' => $data['key']], array_merge($data, [
                'modul'  => 'cabang',
                'urutan' => 10,
                'aktif'  => true,
            ]));
        }

        // ── Tahap 7 D'mentai (2026-09-16) — Backfill tooltip Tahap 2.5/3/5/6 ──
        $this->seedMasterProdukJualTooltips();
        $this->seedMasterBahanBakuTooltips();
        $this->seedPosTransaksiTooltips();
        $this->seedSetoranKasirTooltips();
        $this->seedLaporanSetoranKasirTooltips();
        $this->seedDashboardOwnerTooltips();
    }

    private function seedMasterProdukJualTooltips(): void
    {
        $data = [
            [
                'key'     => 'master_produk_jual.foto',
                'title'   => 'Foto Produk',
                'content' => 'Dipakai sebagai gambar di grid POS supaya kasir cepat mengenali produk. Otomatis di-resize ke 800×800px — upload foto asli tanpa perlu edit ukuran sendiri.',
            ],
            [
                'key'     => 'master_produk_jual.resep',
                'title'   => 'Komposisi / Resep',
                'content' => 'Isi kalau produk ini butuh potong stok bahan otomatis saat laku (mis. Kulit Dimsum, Isian). Kosongkan kalau produk memotong stok dirinya sendiri (mis. minuman kemasan jadi).',
            ],
            [
                'key'     => 'master_produk_jual.mode_harga',
                'title'   => 'Mode Harga Bahan',
                'content' => 'Gratis = bahan ini tidak menambah biaya tampilan HPP. Pakai Master = harga bahan diambil dari Harga Jual master item itu, ikut menambah estimasi HPP produk.',
            ],
            [
                'key'     => 'master_produk_jual.kalkulator',
                'title'   => 'Simulasi Produksi',
                'content' => 'Cek cepat total HPP dan kebutuhan bahan mentah kalau produksi sekian pcs sekaligus — berguna sebelum belanja bahan dalam jumlah besar.',
            ],
            [
                'key'     => 'master_produk_jual.import_bumbu',
                'title'   => 'Import dari Bumbu Pusat',
                'content' => 'Pakai ini kalau bumbu/campuran bahan sudah didaftarkan di Master Bumbu Pusat (mis. "Bumbu Kecap Manis Standar" dipakai banyak produk) — hemat waktu input, dan HPP-nya otomatis ikut update kalau komposisi bumbu diubah nanti. Untuk bahan yang cuma dipakai produk ini saja, pakai "Tambah Bahan" manual.',
            ],
            [
                'key'     => 'master_produk_jual.baris_linked',
                'title'   => 'Baris Terlink',
                'content' => 'Baris ini terlink dari Master Bumbu Pusat, bukan bahan manual. HPP dan potong stok bahan-di-dalamnya dihitung otomatis & selalu ikut komposisi TERBARU di Master Bumbu Pusat — kalau bumbu itu diedit, HPP produk ini ikut berubah tanpa perlu edit di sini.',
            ],
            [
                'key'     => 'master_produk_jual.harga_master_preview',
                'title'   => 'Harga Master (Preview)',
                'content' => 'Harga bahan ini di Master Bahan Baku saat ini — murni tampilan, TIDAK disimpan. Belum ada konversi satuan otomatis (mis. gram vs kg) — kalau angka Subtotal di sebelahnya terlihat aneh, cek satuan & harga bahan itu di Master Bahan Baku.',
            ],
            [
                'key'     => 'master_produk_jual.punya_varian',
                'title'   => 'Punya Varian?',
                'content' => 'Aktifkan kalau produk ini punya pilihan seperti Size atau Rasa dengan harga berbeda-beda. Kasir akan melihat modal pilihan ini saat klik produk di POS.',
            ],
            [
                'key'     => 'master_produk_jual.cabang_aktif',
                'title'   => 'Ketersediaan Outlet',
                'content' => 'Centang outlet yang boleh menjual produk ini — outlet yang tidak dicentang tidak akan menampilkan produk ini sama sekali di POS-nya. Isi Harga Override kalau harga di outlet itu beda dari harga default.',
            ],
        ];
        foreach ($data as $d) {
            Tooltip::updateOrCreate(['key' => $d['key']], array_merge($d, ['modul' => 'master_produk_jual', 'urutan' => 1, 'aktif' => true]));
        }
    }

    private function seedMasterBahanBakuTooltips(): void
    {
        Tooltip::updateOrCreate(['key' => 'master_bahan_baku.stok_awal'], [
            'key'     => 'master_bahan_baku.stok_awal',
            'title'   => 'Stok Awal per Outlet',
            'content' => 'Cuma dipakai sekali saat item baru dibuat, sebagai stok mula-mula. Untuk menambah/mengurangi stok item yang sudah ada, gunakan menu Adjustment Stok, bukan edit item ini.',
            'modul'   => 'master_bahan_baku',
            'urutan'  => 1,
            'aktif'   => true,
        ]);

        // Sprint 4.35 (2026-10-08) — 3 tooltip baru untuk 3 field pindahan dari Master Barang Lengkap.
        Tooltip::updateOrCreate(['key' => 'master_bahan_baku.jenis'], [
            'key'     => 'master_bahan_baku.jenis',
            'title'   => 'Jenis Item',
            'content' => 'Bahan Baku = masuk produksi (tepung, daging, saus, bumbu). Perlengkapan = ATK/habis pakai yang tidak masuk produksi (nota kertas, label print, isolasi). Beda jenis mempengaruhi mapping COA di laporan akuntansi.',
            'modul'   => 'master_bahan_baku',
            'urutan'  => 2,
            'aktif'   => true,
        ]);

        Tooltip::updateOrCreate(['key' => 'master_bahan_baku.track_stok'], [
            'key'     => 'master_bahan_baku.track_stok',
            'title'   => 'Lacak Stok',
            'content' => 'Centang (default) kalau qty-nya perlu dihitung ketat per unit — mayoritas bahan baku & kemasan. Uncheck untuk ATK yang tidak perlu dihitung ketat (mis. isolasi, pena, karet gelang). Barang non-track tidak akan ikut dialert kalau stok habis.',
            'modul'   => 'master_bahan_baku',
            'urutan'  => 3,
            'aktif'   => true,
        ]);

        Tooltip::updateOrCreate(['key' => 'master_bahan_baku.deskripsi'], [
            'key'     => 'master_bahan_baku.deskripsi',
            'title'   => 'Deskripsi',
            'content' => 'Opsional. Catatan tambahan tentang barang: ukuran, merek pilihan, supplier preferensi, instruksi khusus, dll. Tampil di halaman detail item.',
            'modul'   => 'master_bahan_baku',
            'urutan'  => 4,
            'aktif'   => true,
        ]);
    }

    private function seedPosTransaksiTooltips(): void
    {
        $data = [
            [
                'key'     => 'pos.tipe_transaksi',
                'title'   => 'Tipe Transaksi',
                'content' => 'Dine-in butuh Nomor Meja (kalau diaktifkan outlet ini), Takeaway kena Takeaway Fee otomatis kalau outlet men-setnya. Cuma tipe yang aktif di outlet ini yang tampil.',
            ],
            [
                'key'     => 'pos.nomor_meja',
                'title'   => 'Nomor Meja',
                'content' => 'Wajib diisi untuk transaksi Dine-in kalau outlet mengaktifkan fitur ini — membantu pelayan mengantar pesanan ke meja yang benar.',
            ],
            [
                'key'     => 'pos.item_tambahan',
                'title'   => 'Item Tambahan',
                'content' => 'Item 1-klik seperti garpu (gratis) atau saus extra (berbayar) — klik langsung masuk keranjang tanpa perlu cari di grid produk utama.',
            ],
            [
                'key'     => 'pos.split_payment',
                'title'   => 'Split Payment',
                'content' => 'Bagi 1 tagihan ke beberapa metode bayar sekaligus, misal sebagian Tunai dan sebagian QRIS — klik "+ Tambah Metode Bayar" untuk menambah baris pembayaran baru.',
            ],
            [
                'key'     => 'pos.bill_tersimpan',
                'title'   => 'Bill Tersimpan',
                'content' => 'Order yang disimpan lewat "Save Bill" (belum bayar, belum potong stok). "Tunai Pas" = bayar cepat tunai sejumlah tagihan. "Bayar..." = pilih metode lain / split payment. Tombol Batalkan (ikon silang merah) hanya muncul untuk user dengan izin "Batalkan Bill Tersimpan".',
            ],
        ];
        foreach ($data as $d) {
            Tooltip::updateOrCreate(['key' => $d['key']], array_merge($d, ['modul' => 'pos', 'urutan' => 20, 'aktif' => true]));
        }
    }

    private function seedSetoranKasirTooltips(): void
    {
        $data = [
            [
                'key'     => 'setoran_kasir.total_disetor',
                'title'   => 'Jumlah Uang Tunai yang Diserahkan',
                'content' => 'Isi uang tunai fisik yang kamu serahkan ke HO. Kalau beda dari total sistem, selisihnya otomatis tercatat dan tampil ke HO saat approval.',
            ],
            [
                'key'     => 'setoran_kasir.bukti_foto',
                'title'   => 'Bukti Foto',
                'content' => 'Opsional — foto uang tunai atau catatan hitung fisik, membantu HO verifikasi kalau ada selisih besar.',
            ],
            [
                'key'     => 'setoran_kasir.catatan_kasir',
                'title'   => 'Catatan',
                'content' => 'Jelaskan kalau ada selisih (misal: "kurang Rp5.000 karena kembalian kurang pas") — memudahkan HO memahami tanpa perlu tanya balik.',
            ],
            [
                'key'     => 'setoran_kasir.alasan_reject',
                'title'   => 'Alasan Penolakan',
                'content' => 'Wajib diisi jelas dan spesifik — ini yang akan dibaca kasir untuk tahu apa yang perlu diperbaiki sebelum submit ulang.',
            ],
            [
                'key'     => 'setoran_kasir.catatan_ho',
                'title'   => 'Catatan HO',
                'content' => 'Opsional, cuma tampil ke kasir setelah setoran disetujui — bisa dipakai untuk apresiasi atau catatan administratif ringan.',
            ],
        ];
        foreach ($data as $d) {
            Tooltip::updateOrCreate(['key' => $d['key']], array_merge($d, ['modul' => 'setoran_kasir', 'urutan' => 1, 'aktif' => true]));
        }
    }

    private function seedLaporanSetoranKasirTooltips(): void
    {
        $data = [
            [
                'key'     => 'laporan_setoran_kasir.filter_tanggal',
                'title'   => 'Rentang Tanggal',
                'content' => 'Filter berdasarkan tanggal setoran (bukan tanggal kapan disubmit) — default menampilkan bulan berjalan.',
            ],
            [
                'key'     => 'laporan_setoran_kasir.filter_status',
                'title'   => 'Filter Status',
                'content' => 'Menunggu = belum diproses HO, Disetujui = uang sudah masuk Kas HO, Ditolak = kasir perlu revisi & submit ulang.',
            ],
        ];
        foreach ($data as $d) {
            Tooltip::updateOrCreate(['key' => $d['key']], array_merge($d, ['modul' => 'laporan_setoran_kasir', 'urutan' => 1, 'aktif' => true]));
        }
    }

    private function seedDashboardOwnerTooltips(): void
    {
        $data = [
            [
                'key'     => 'dashboard_owner.uang_belum_disetor',
                'title'   => 'Uang Belum Disetor',
                'content' => 'Total setoran yang statusnya masih Menunggu atau Ditolak — hari yang kasirnya belum submit sama sekali TIDAK ikut terhitung di sini.',
            ],
            [
                'key'     => 'dashboard_owner.kas_ho',
                'title'   => 'Kas HO Saat Ini',
                'content' => 'Saldo kas tunai di Gudang Pusat — naik otomatis setiap kali Setoran Kasir dari outlet manapun di-approve.',
            ],
        ];
        foreach ($data as $d) {
            Tooltip::updateOrCreate(['key' => $d['key']], array_merge($d, ['modul' => 'dashboard_owner', 'urutan' => 1, 'aktif' => true]));
        }
    }
}
