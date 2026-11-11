<?php

use App\Http\Controllers\AntrianDisplayController;
use App\Http\Controllers\AntrianCekController;
use App\Http\Controllers\AntrianOperatorController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CabangController;
use App\Http\Controllers\CabangSwitcherController;
use App\Http\Controllers\TrashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LaporanAsetController;
use App\Http\Controllers\LaporanBepController;
use App\Http\Controllers\LaporanHRController;
use App\Http\Controllers\LaporanKeuanganController;
use App\Http\Controllers\LaporanPenjualanController;
use App\Http\Controllers\LaporanStokController;
use App\Http\Controllers\PelangganController;
use App\Http\Controllers\PenjualanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PoDashboardController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StockRequestController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StokController;
use App\Http\Controllers\StokDashboardController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\PenggajianController;
use App\Http\Controllers\CutiController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\KeuanganController;
use App\Http\Controllers\KeuanganDashboardController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\BepController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\FaceRegistrationController;
use App\Http\Controllers\FaceAttendanceController;
use App\Http\Controllers\AbsenDeviceController;
use App\Http\Controllers\LaporanAbsensiController;
use App\Http\Controllers\PengaturanGajiController;
use App\Http\Controllers\PengaturanUmumController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\AbsensiSayaController;
use App\Http\Controllers\HariLiburController;
use App\Http\Controllers\DashboardAbsensiController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\KategoriTransaksiController;
use App\Http\Controllers\SetoranController;
use App\Http\Controllers\TransferAntarKasController;
use App\Http\Controllers\LaporanSetoranController;
use App\Http\Controllers\LaporanKategoriController;
use App\Http\Controllers\LaporanAuditBuktiController;
use App\Http\Controllers\LaporanSaldoKasController;
use App\Http\Controllers\LaporanCabangController;
use App\Http\Controllers\SetoranHarianController;
use App\Http\Controllers\LaporanKonsumsiBahanController;
use App\Http\Controllers\LaporanLabaRugiController;
use App\Http\Controllers\LaporanNeracaController;
use App\Http\Controllers\LaporanLabaRugiFormalController;
use App\Http\Controllers\LaporanBepOtomatisController;
use App\Http\Controllers\BukuBesarController;
use App\Http\Controllers\LaporanEksekutifController;
use App\Http\Controllers\LaporanJamRamaiController;
use App\Http\Controllers\SimulatorBepController;
use App\Http\Controllers\RecurringTransaksiController;
use App\Http\Controllers\Admin\TooltipController as AdminTooltipController;
use App\Http\Controllers\PanduanController;
use App\Http\Controllers\Admin\PanduanController as AdminPanduanController;
use App\Http\Controllers\Master\JenisOlahanController;
use App\Http\Controllers\Master\MasterResepBumbuController;
use App\Http\Controllers\LoyaltyKlaimController;
use App\Http\Controllers\LoyaltyProgramController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Serve uploaded files from storage — works without symlink (needed on Rumahweb / shared hosting)
Route::get('/img/{path}', function (string $path) {
    $fullPath = storage_path('app/public/' . ltrim($path, '/'));
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        abort(404);
    }
    $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
    return response()->file($fullPath, [
        'Content-Type'  => $mime,
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->where('path', '.*')->name('img.serve');

// Serve foto produk dkk. (Storage::disk('public')) — pasangan
// App\Support\StorageAwareUrlGenerator yang override asset('storage/xxx')
// jadi asset/xxx. Prinsip sama dgn /img/{path} di atas (bypass rule
// .htaccess bawaan yg blokir /storage/ di shared hosting tanpa symlink),
// pola controller dipakai (bukan closure) supaya reusable/testable.
Route::get('/asset/{path}', [\App\Http\Controllers\StorageAssetController::class, 'show'])
    ->where('path', '.*')->name('storage.asset');

// ===== PWA — STANDALONE (tanpa auth, bypass layout) =====
Route::get('/serviceworker.js', function () {
    return response()->file(public_path('serviceworker.js'), [
        'Content-Type'         => 'application/javascript',
        'Service-Worker-Allowed' => '/',
        'Cache-Control'        => 'no-cache',
    ]);
})->name('pwa.serviceworker');

Route::get('/offline.html', function () {
    return response()->file(public_path('offline.html'), [
        'Content-Type' => 'text/html; charset=UTF-8',
    ]);
})->name('pwa.offline');

// 4.44 Poin 1 — identitas usaha untuk aplikasi kasir Android "Kasir POS".
// Publik: hanya identitas yang memang tampil di halaman login. APK menolak
// alamat ERP yang tidak punya endpoint ini (erp: true). Format JSON = kontrak APK.
Route::get('/app-info', function (\App\Support\Branding $brand) {
    return response()->json(['erp' => true] + $brand->untukAplikasi());
})->name('app.info');

// ===== ANTRIAN PRODUKSI — DISPLAY PUBLIK (tanpa auth) =====
Route::get('/antrian/display/{cabang}', [AntrianDisplayController::class, 'show'])->name('antrian.display');
Route::get('/antrian/data/{cabang}',    [AntrianDisplayController::class, 'data'])->name('antrian.data');

Route::middleware(['auth', 'verified', 'cabang'])->group(function () {

    // 4.44 Poin 2 — Label Produk (stiker) per order. Izin & cabang dicek di controller.
    Route::get('/label-cup/order/{order}', [\App\Http\Controllers\CupLabelController::class, 'order'])->name('label-cup.order');

    // Dashboard
    // Struk modal — AJAX partial, tanpa layout app
    Route::get('/penjualan/{order}/struk-modal', [PenjualanController::class, 'strukModal'])->name('penjualan.struk-modal');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/cabang', [DashboardController::class, 'cabang'])->name('dashboard.cabang');
    Route::get('/dashboard/gudang', [DashboardController::class, 'gudang'])->name('dashboard.gudang');
    Route::get('/dashboard/pusat', [DashboardController::class, 'pusat'])->name('dashboard.pusat');

    // Ganti cabang aktif (semua role bisa)
    Route::post('/cabang/switch', [CabangSwitcherController::class, 'switch'])->name('cabang.switch');

    // ===== MANAJEMEN CABANG (Owner / Admin Pusat) =====
    // 4.43: per aksi mengikuti centangan Role & Hak Akses (dulu role:owner,admin_pusat)
    Route::resource('cabang', CabangController::class)->only(['create', 'store'])->middleware('can:cabang.create');
    Route::resource('cabang', CabangController::class)->only(['index', 'show'])->middleware('can:cabang.view');
    Route::resource('cabang', CabangController::class)->only(['edit', 'update'])->middleware('can:cabang.edit');
    Route::resource('cabang', CabangController::class)->only(['destroy'])->middleware('can:cabang.delete');
    Route::middleware('can:cabang.edit')->group(function () {
        Route::patch('/cabang/{cabang}/toggle-aktif', [CabangController::class, 'toggleAktif'])
            ->name('cabang.toggle-aktif');
        Route::post('/cabang/{cabang}/assign-user', [CabangController::class, 'assignUser'])
            ->name('cabang.assign-user');
        Route::delete('/cabang/{cabang}/remove-user', [CabangController::class, 'removeUser'])
            ->name('cabang.remove-user');
    });

    // ===== STOK & INVENTORI =====
    // Item (master barang) — owner, admin_pusat, admin_gudang
    // 4.43: Semua Barang ikut centangan item.view (dulu role:owner,admin_pusat,admin_gudang);
    // tambah kategori dicek di controller (dipakai form Bahan Baku & Produk Jual).
    Route::post('/item/kategori', [ItemController::class, 'storeKategori'])->name('item.kategori.store');
    Route::resource('item', ItemController::class)->middleware('can:item.view');

    // ===== Tahap 2.5 D'mentai — split Master Item jadi 2 menu (permission-
    // based, bukan role hardcode, supaya manajer_cabang yang di-grant
    // master.bahan_baku.*/master.produk_jual.* bisa akses) =====
    Route::prefix('master/bahan-baku')->name('master.bahan-baku.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MasterBahanBakuController::class, 'index'])->name('index')->middleware('can:master.bahan_baku.view');
        Route::get('/create', [\App\Http\Controllers\MasterBahanBakuController::class, 'create'])->name('create')->middleware('can:master.bahan_baku.create');
        Route::post('/', [\App\Http\Controllers\MasterBahanBakuController::class, 'store'])->name('store')->middleware('can:master.bahan_baku.create');
        Route::get('/{bahanBaku}/edit', [\App\Http\Controllers\MasterBahanBakuController::class, 'edit'])->name('edit')->middleware('can:master.bahan_baku.edit');
        Route::put('/{bahanBaku}', [\App\Http\Controllers\MasterBahanBakuController::class, 'update'])->name('update')->middleware('can:master.bahan_baku.edit');
        Route::delete('/{bahanBaku}', [\App\Http\Controllers\MasterBahanBakuController::class, 'destroy'])->name('destroy')->middleware('can:master.bahan_baku.delete');
    });

    Route::prefix('master/produk-jual')->name('master.produk-jual.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MasterProdukJualController::class, 'index'])->name('index')->middleware('can:master.produk_jual.view');
        // Wajib di atas '/create' & '/{produkJual}/edit' -- static path 'bumbu-pusat'
        // supaya tidak ketangkep route model binding {produkJual}.
        Route::get('/bumbu-pusat/list', [\App\Http\Controllers\MasterProdukJualController::class, 'listBumbuPusat'])->name('bumbu-pusat.list')->middleware('can:master.produk_jual.edit');
        // Preview subtotal 1 Bumbu Pusat -- BUKAN bind ke {produkJual} (bug fix
        // 2026-09-19: subtotal 1 bumbu tidak butuh produk tersimpan, lihat
        // CLAUDE.md 4.19), jadi jalan di halaman Create maupun Edit.
        Route::post('/preview-bumbu/{bumbu}', [\App\Http\Controllers\MasterProdukJualController::class, 'previewSubtotalBumbu'])->name('preview-bumbu')->middleware('can:master.produk_jual.edit');
        // 4.42 — Ambil produk dari cabang lain (salin jadi milik cabang tujuan)
        Route::get('/ambil', [\App\Http\Controllers\AmbilProdukCabangController::class, 'index'])->name('ambil.index')->middleware('can:master.produk_jual.create');
        Route::get('/ambil/periksa', [\App\Http\Controllers\AmbilProdukCabangController::class, 'periksa'])->name('ambil.periksa')->middleware('can:master.produk_jual.create');
        Route::post('/ambil', [\App\Http\Controllers\AmbilProdukCabangController::class, 'simpan'])->name('ambil.simpan')->middleware('can:master.produk_jual.create');
        Route::get('/create', [\App\Http\Controllers\MasterProdukJualController::class, 'create'])->name('create')->middleware('can:master.produk_jual.create');
        Route::post('/', [\App\Http\Controllers\MasterProdukJualController::class, 'store'])->name('store')->middleware('can:master.produk_jual.create');
        Route::get('/{produkJual}/edit', [\App\Http\Controllers\MasterProdukJualController::class, 'edit'])->name('edit')->middleware('can:master.produk_jual.edit');
        Route::put('/{produkJual}', [\App\Http\Controllers\MasterProdukJualController::class, 'update'])->name('update')->middleware('can:master.produk_jual.edit');
        Route::delete('/{produkJual}', [\App\Http\Controllers\MasterProdukJualController::class, 'destroy'])->name('destroy')->middleware('can:master.produk_jual.delete');
        Route::match(['get', 'post'], '/{produkJual}/kalkulator-resep', [\App\Http\Controllers\MasterProdukJualController::class, 'kalkulatorResep'])->name('kalkulator-resep')->middleware('can:master.produk_jual.view');
    });

    // ===== PEMAKAIAN PERLENGKAPAN (Fase 5, Rule #66) — menu BARU, terpisah
    // dari Adjustment Stok existing. Permission-based (bukan role hardcode). =====
    Route::resource('pemakaian-perlengkapan', \App\Http\Controllers\PemakaianPerlengkapanController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->middleware([
            'index'  => 'can:pemakaian_perlengkapan.view',
            'create' => 'can:pemakaian_perlengkapan.create',
            'store'  => 'can:pemakaian_perlengkapan.create',
            'show'   => 'can:pemakaian_perlengkapan.view',
        ]);

    // Stok — semua role
    Route::get('/stok/dashboard', [StokDashboardController::class, 'index'])->name('stok.dashboard');
    Route::get('/stok/dashboard/item/{item}/batches', [StokDashboardController::class, 'itemBatches'])->name('stok.dashboard.item-batches');
    Route::get('/stok/aging', [StokDashboardController::class, 'aging'])->name('stok.aging');
    Route::get('/stok/top-movement', [StokDashboardController::class, 'topMovement'])->name('stok.top-movement');
    Route::get('/stok/trend-harga', [StokDashboardController::class, 'trendHarga'])->name('stok.trend-harga');
    Route::get('/stok/stok-mati', [StokDashboardController::class, 'stokMati'])->name('stok.stok-mati');
    Route::get('/stok', [StokController::class, 'index'])->name('stok.index');
    Route::get('/stok/kartu', [StokController::class, 'kartu'])->name('stok.kartu');
    Route::get('/stok/adjustment', [StokController::class, 'adjustmentForm'])->name('stok.adjustment');
    Route::post('/stok/adjustment', [StokController::class, 'adjustmentStore'])->name('stok.adjustment.store');
    Route::patch('/stok/{stock}/set-minimum', [StokController::class, 'setMinimum'])
        ->name('stok.set-minimum')
        ->middleware('can:stok.minimum.set');
    Route::delete('/stok/{stock}/reset', [StokController::class, 'reset'])
        ->name('stok.reset')
        ->middleware('can:stok.hapus.reset');
    Route::get('/api/stok/qty', [StokController::class, 'apiQty'])->name('api.stok.qty');
    Route::get('/api/stok/batches', [StokController::class, 'apiBatches'])->name('api.stok.batches');

    // Stock Request (permintaan bahan)
    Route::resource('stock-request', StockRequestController::class)->only(['index','create','store','show','destroy']);
    Route::post('/stock-request/{stockRequest}/approve', [StockRequestController::class, 'approve'])->name('stock-request.approve');
    Route::post('/stock-request/{stockRequest}/terima', [StockRequestController::class, 'terima'])->name('stock-request.terima');
    Route::post('/stock-request/{stockRequest}/batalkan', [StockRequestController::class, 'batalkan'])->name('stock-request.batalkan');

    // Stock Transfer
    Route::resource('stock-transfer', StockTransferController::class)->only(['index','create','store','show','destroy']);
    Route::post('/stock-transfer/{stockTransfer}/kirim', [StockTransferController::class, 'kirim'])->name('stock-transfer.kirim');
    Route::post('/stock-transfer/{stockTransfer}/terima', [StockTransferController::class, 'terima'])->name('stock-transfer.terima');
    Route::post('/stock-transfer/{stockTransfer}/batalkan', [StockTransferController::class, 'batalkan'])->name('stock-transfer.batalkan');

    // ===== BABA Tahap 2a — TOKO ONLINE (produk & harga untuk menu Belanja aplikasi member) =====
    Route::prefix('toko-online')->name('toko-online.')->controller(\App\Http\Controllers\ProdukOnlineController::class)->group(function () {
        Route::get('/produk', 'index')->name('produk.index');
        Route::get('/produk/create', 'create')->name('produk.create');
        Route::post('/produk', 'store')->name('produk.store');
        Route::get('/produk/{produk}/edit', 'edit')->name('produk.edit');
        Route::put('/produk/{produk}', 'update')->name('produk.update');
        Route::get('/cari-akun', 'cariAkun')->name('cari-akun');
    });
    // BABA Tahap 2b — pesanan online (gudang memproses) & pengaturan toko online
    Route::prefix('toko-online')->name('toko-online.')->group(function () {
        Route::controller(\App\Http\Controllers\PesananOnlineController::class)->group(function () {
            Route::get('/pesanan', 'index')->name('pesanan.index');
            Route::get('/pesanan/{pesanan}', 'show')->name('pesanan.show');
            Route::post('/pesanan/{pesanan}/ongkir', 'ongkir')->name('pesanan.ongkir');
            Route::post('/pesanan/{pesanan}/bayar/{bayar}/terima', 'terimaBukti')->name('pesanan.bukti.terima');
            Route::post('/pesanan/{pesanan}/bayar/{bayar}/tolak', 'tolakBukti')->name('pesanan.bukti.tolak');
            Route::get('/pesanan/{pesanan}/bayar/{bayar}/file', 'lihatBukti')->name('pesanan.bukti.file');
            Route::post('/pesanan/{pesanan}/keluarkan', 'keluarkan')->name('pesanan.keluarkan');
            Route::post('/pesanan/{pesanan}/sudah-diambil', 'sudahDiambil')->name('pesanan.diambil');
            Route::post('/pesanan/{pesanan}/batal', 'batal')->name('pesanan.batal');
        });
        Route::controller(\App\Http\Controllers\TokoOnlinePengaturanController::class)->group(function () {
            Route::get('/pengaturan', 'index')->name('pengaturan');
            Route::put('/pengaturan/umum', 'simpanUmum')->name('pengaturan.umum');
            Route::post('/pengaturan/rekening', 'simpanRekening')->name('pengaturan.rekening');
            Route::put('/pengaturan/rekening/{rekening}', 'simpanRekening')->name('pengaturan.rekening.ubah');
            Route::post('/pengaturan/rekening/{rekening}/aktif', 'aktifRekening')->name('pengaturan.rekening.aktif');
            Route::post('/pengaturan/tagihan', 'simpanAturan')->name('pengaturan.tagihan');
            Route::post('/pengaturan/tagihan/{aturan}/nonaktif', 'nonaktifAturan')->name('pengaturan.tagihan.nonaktif');
            Route::post('/pengaturan/sumber', 'simpanSumber')->name('pengaturan.sumber');
            Route::post('/pengaturan/sumber/{sumber}/nonaktif', 'nonaktifSumber')->name('pengaturan.sumber.nonaktif');
        });
        // Tahap 2c — stok parfum milik member (pantau saja)
        Route::get('/stok-member', [\App\Http\Controllers\StokMemberController::class, 'index'])->name('stok-member.index');
        Route::get('/stok-member/{memberId}', [\App\Http\Controllers\StokMemberController::class, 'show'])->whereNumber('memberId')->name('stok-member.show');
    });

    // ===== 4.49 Tahap B1 — PRODUKSI bahan setengah jadi di gudang (porting erp-coffeeshop) =====
    Route::prefix('produksi')->name('produksi.')->controller(\App\Http\Controllers\ProduksiController::class)->group(function () {
        Route::get('/resep', 'resepIndex')->name('resep.index');
        Route::get('/resep/create', 'resepCreate')->name('resep.create');
        Route::post('/resep', 'resepStore')->name('resep.store');
        Route::get('/resep/{resep}/edit', 'resepEdit')->name('resep.edit');
        Route::put('/resep/{resep}', 'resepUpdate')->name('resep.update');
        Route::delete('/resep/{resep}', 'resepDestroy')->name('resep.destroy');

        // BABA Tahap 1b — Buat Parfum
        Route::get('/parfum-baru', 'parfumCreate')->name('parfum.create');
        Route::post('/parfum-baru', 'parfumStore')->name('parfum.store');
        // BABA Tahap 1a — konsentrasi parfum & selesai peram
        Route::get('/konsentrasi', 'konsentrasiIndex')->name('konsentrasi.index');
        Route::post('/konsentrasi', 'konsentrasiStore')->name('konsentrasi.store');
        Route::put('/konsentrasi/{konsentrasi}', 'konsentrasiUpdate')->name('konsentrasi.update');

        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{produksi}', 'show')->name('show');
        Route::post('/{produksi}/batalkan', 'batalkan')->name('batalkan');
        Route::post('/{produksi}/selesai-peram', 'selesaiPeram')->name('selesai-peram');
    });

    // ===== 4.49 Tahap B2 — KIRIMAN CABANG (Gudang Pusat → cabang, Terima/Tolak/Batalkan) =====
    Route::prefix('kiriman')->name('kiriman.')->controller(\App\Http\Controllers\KirimanCabangController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{order}', 'show')->name('show');
        Route::post('/{order}/terima', 'terima')->name('terima');
        Route::post('/{order}/tolak', 'tolak')->name('tolak');
        Route::post('/{order}/batalkan', 'batalkan')->name('batalkan');
    });

    // ===== 4.49 Tahap B3 — RETUR CABANG → Gudang (porting erp-coffeeshop) =====
    Route::prefix('retur-cabang')->name('retur-cabang.')->controller(\App\Http\Controllers\ReturInternalController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/buat/{order}', 'create')->name('create');
        Route::post('/buat/{order}', 'store')->name('store');
        Route::get('/{retur}', 'show')->name('show');
        Route::post('/{retur}/terima', 'terima')->name('terima');
        Route::post('/{retur}/tolak', 'tolak')->name('tolak');
        Route::post('/{retur}/batalkan', 'batalkan')->name('batalkan');
    });

    // ===== MANAJEMEN USER (Owner / Admin Pusat / Manajer Cabang) =====
    // 4.43: per aksi mengikuti centangan (dulu role:owner,admin_pusat,manajer_cabang)
    Route::resource('user', UserController::class)->only(['create', 'store'])->middleware('can:user.create');
    Route::resource('user', UserController::class)->only(['index', 'show'])->middleware('can:user.view');
    Route::resource('user', UserController::class)->only(['edit', 'update'])->middleware('can:user.edit');
    Route::resource('user', UserController::class)->only(['destroy'])->middleware('can:user.delete');
    Route::middleware('can:user.edit')->group(function () {
        Route::patch('/user/{user}/toggle-aktif', [UserController::class, 'toggleAktif'])
            ->name('user.toggle-aktif');
        Route::post('/user/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('user.reset-password');
    });

    // ===== MANAJEMEN ROLE & PERMISSION (Owner only) =====
    Route::middleware('role:owner')->group(function () {
        Route::get('/role', [RoleController::class, 'index'])->name('role.index');
        Route::post('/role/{role}/permissions', [RoleController::class, 'updatePermissions'])
            ->name('role.update-permissions');
        // Tahap D 4.38 — role buatan Owner (mis. Kru)
        Route::post('/role-buatan', [RoleController::class, 'storeCustom'])->name('role.custom.store');
        Route::put('/role-buatan/{customRole}', [RoleController::class, 'updateCustom'])->name('role.custom.update');
        Route::delete('/role-buatan/{customRole}', [RoleController::class, 'destroyCustom'])->name('role.custom.destroy');

        Route::get('/pengaturan/umum', [PengaturanUmumController::class, 'edit'])->name('pengaturan.umum');
        Route::put('/pengaturan/umum', [PengaturanUmumController::class, 'update'])->name('pengaturan.umum.update');

        // Modal Owner (NeracaSetting) — figure "plug" manual untuk Neraca,
        // Owner-only konsisten dengan Pengaturan Penggajian/Umum di atas.
        Route::put('/laporan/neraca/setting', [LaporanNeracaController::class, 'updateSetting'])
            ->name('laporan.neraca.update-setting');
    });

    // Pengaturan Penggajian — 4.43: ikut centangan (dulu khusus Owner)
    Route::middleware('can:pengaturan_penggajian')->group(function () {
        Route::get('/pengaturan/penggajian', [PengaturanGajiController::class, 'edit'])->name('pengaturan.penggajian');
        Route::put('/pengaturan/penggajian', [PengaturanGajiController::class, 'update'])->name('pengaturan.penggajian.update');
    });

    // ===== PROFIL =====
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ===== SUPPLIER (owner, admin_pusat, admin_gudang) =====
    // 4.43: ikut centangan supplier.view / supplier.manage (dulu role:owner,admin_pusat,admin_gudang)
    Route::resource('supplier', SupplierController::class)->only(['create', 'store', 'edit', 'update', 'destroy'])->middleware('can:supplier.manage');
    Route::resource('supplier', SupplierController::class)->only(['index', 'show'])->middleware('can:supplier.view');
    Route::patch('/supplier/{supplier}/toggle-aktif', [SupplierController::class, 'toggleAktif'])
        ->name('supplier.toggle-aktif')->middleware('can:supplier.manage');

    // ===== PEMBELIAN / PURCHASE ORDER (permission-based per aksi) =====
    Route::prefix('pembelian')->name('pembelian.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])
            ->name('index')->middleware('can:pembelian.view');
        Route::get('/create', [PurchaseOrderController::class, 'create'])
            ->name('create')->middleware('can:pembelian.create');
        Route::post('/', [PurchaseOrderController::class, 'store'])
            ->name('store')->middleware('can:pembelian.create');

        // Dashboard PO — monitoring end-to-end (read-only, permission terpisah
        // dari pembelian.*). Static routes WAJIB sebelum wildcard /{purchaseOrder}.
        Route::get('/po-dashboard', [PoDashboardController::class, 'index'])
            ->name('po-dashboard.index')->middleware('can:po_dashboard.view');
        Route::get('/po-dashboard/print', [PoDashboardController::class, 'print'])
            ->name('po-dashboard.print')->middleware('can:po_dashboard.view');
        Route::get('/po-dashboard/export', [PoDashboardController::class, 'export'])
            ->name('po-dashboard.export')->middleware('can:po_dashboard.view');

        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])
            ->name('show')->middleware('can:pembelian.view');

        // Cleanup Tool B1 — link transaksi keuangan historis yang belum ter-link
        // ke PO ini (Owner-only, permission terpisah dari pembelian.*).
        Route::get('/{purchaseOrder}/cari-transaksi', [PurchaseOrderController::class, 'cariTransaksi'])
            ->name('cari-transaksi')->middleware('can:transaksi.link_po.action');
        Route::post('/{purchaseOrder}/link-transaksi', [PurchaseOrderController::class, 'linkTransaksi'])
            ->name('link-transaksi')->middleware('can:transaksi.link_po.action');

        // Batal Bayar PO (Fase 4) — rollback pembayaran, permission terpisah
        // dari pembelian.* (default Owner + admin_pusat via RolePermissionSeeder).
        Route::post('/{purchaseOrder}/batal-bayar', [PurchaseOrderController::class, 'batalBayar'])
            ->name('batal-bayar')->middleware('can:po.batal_bayar.action');

        Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])
            ->name('destroy')->middleware('can:pembelian.delete');
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
            ->name('approve')->middleware('can:pembelian.approve');
        Route::post('/{purchaseOrder}/kirim-supplier', [PurchaseOrderController::class, 'kirimSupplier'])
            ->name('kirim-supplier')->middleware('can:pembelian.kirim-supplier');
        Route::post('/{purchaseOrder}/terima', [PurchaseOrderController::class, 'terima'])
            ->name('terima')->middleware('can:pembelian.terima');
        Route::post('/{purchaseOrder}/batalkan', [PurchaseOrderController::class, 'batalkan'])
            ->name('batalkan')->middleware('can:pembelian.delete');
    });

    // ===== ANTRIAN PRODUKSI — OPERATOR (auth) =====
    Route::get('/antrian/operator', [AntrianOperatorController::class, 'index'])->name('antrian.operator');
    Route::post('/antrian/{order}/mulai-kerja', [AntrianOperatorController::class, 'mulaiKerja'])->name('antrian.mulai-kerja');
    Route::post('/antrian/{order}/selesai',     [AntrianOperatorController::class, 'selesai'])->name('antrian.selesai');
    Route::post('/antrian/{order}/simpan-rak',  [AntrianOperatorController::class, 'simpanRak'])->name('antrian.simpan-rak');
    Route::post('/antrian/{order}/diambil',     [AntrianOperatorController::class, 'diambil'])->name('antrian.diambil');
    Route::post('/antrian/{order}/ambil-alih',  [AntrianOperatorController::class, 'ambilAlih'])->name('antrian.ambil-alih');

    // ===== ANTRIAN PRODUKSI — MODE PRODUKSI (tablet operator) =====
    Route::get('/antrian/produksi',           [AntrianOperatorController::class, 'produksi'])->name('antrian.produksi');
    Route::get('/antrian/produksi/data',      [AntrianOperatorController::class, 'produksiData'])->name('antrian.produksi.data');
    Route::get('/antrian/produksi/karyawan',  [AntrianOperatorController::class, 'karyawanListProduksi'])->name('antrian.produksi.karyawan');

    // ===== ANTRIAN PRODUKSI — CEK ANTRIAN (kasir / semua role dengan antrian.lihat) =====
    Route::get('/antrian/cek',                         [AntrianCekController::class, 'index'])->name('antrian.cek');
    Route::get('/antrian/cek/search',                  [AntrianCekController::class, 'search'])->name('antrian.cek.search');
    Route::post('/antrian/cek/{order}/tandai-diambil', [AntrianCekController::class, 'tandaiDiambil'])->name('antrian.cek.diambil');
    Route::get('/antrian/cek/{order}/cetak-ulang',     [AntrianCekController::class, 'cetakUlang'])->name('antrian.cek.cetak-ulang');

    // ===== PENJUALAN / POS =====
    // 4.43: POS & transaksi baru = order.create, riwayat = order.view (dulu semua role).
    // Detail order & struk dicek di controller (dibuka juga dari Antrian/Kas).
    Route::middleware('can:order.create')->group(function () {
        Route::get('/penjualan/pos', [PenjualanController::class, 'pos'])->name('penjualan.pos');
        // Helper AJAX auto-populate resep bumbu di POS — sengaja TIDAK digated
        // permission master.resep_bumbu.* (itu utk CRUD master data), kasir tetap
        // perlu bisa pakai resep walau tidak boleh kelola master-nya.
        Route::get('/pos/resep-bumbu/{resepBumbu}', [PenjualanController::class, 'resepBumbuItems'])->name('pos.resep-bumbu');
        // Tahap 3 D'mentai — modal varian, Save Bill, Charge Bill, preview cetak
        Route::get('/pos/item/{item}/varian', [PenjualanController::class, 'itemVarian'])->name('pos.item-varian');
        Route::post('/penjualan/simpan-bill', [PenjualanController::class, 'simpanBill'])->name('penjualan.simpan-bill');
        Route::post('/penjualan/{order}/charge', [PenjualanController::class, 'chargeBill'])->name('penjualan.charge-bill');
        Route::post('/penjualan/{order}/batalkan-bill', [PenjualanController::class, 'batalkanBill'])->name('penjualan.batalkan-bill')->middleware('can:order.bill_tersimpan.batalkan');
        Route::post('/penjualan/print-preview', [PenjualanController::class, 'printBillPreview'])->name('penjualan.print-preview');
        Route::post('/penjualan', [PenjualanController::class, 'store'])->name('penjualan.store');
        // Wajib di atas Route::get('/penjualan/{order}') — kalau di bawah, "cek-pengganti"
        // akan ketangkep sebagai {order} (string) duluan lalu 404 di route model binding.
        Route::get('/penjualan/cek-pengganti', [PenjualanController::class, 'cekPengganti'])->name('penjualan.cek-pengganti');
    });
    // 4.46 — Laporan di menu Penjualan (bisa dibuka kasir). Izin dicek di controller.
    Route::get('/penjualan/laporan/transaksi', [\App\Http\Controllers\LaporanTransaksiKasirController::class, 'index'])->name('penjualan.laporan.transaksi');
    Route::get('/penjualan/laporan/produk', [\App\Http\Controllers\LaporanProdukKasirController::class, 'index'])->name('penjualan.laporan.produk');
    Route::get('/penjualan/laporan/kategori', [\App\Http\Controllers\LaporanKategoriKasirController::class, 'index'])->name('penjualan.laporan.kategori');
    Route::get('/penjualan', [PenjualanController::class, 'index'])->name('penjualan.index')->middleware('can:order.view');
    Route::get('/penjualan/{order}', [PenjualanController::class, 'show'])->name('penjualan.show');
    Route::get('/penjualan/{order}/struk', [PenjualanController::class, 'struk'])->name('penjualan.struk');
    Route::post('/penjualan/{order}/batalkan', [PenjualanController::class, 'batalkan'])->name('penjualan.batalkan')->middleware('can:order.batalkan');
    Route::post('/penjualan/{order}/kembalikan', [PenjualanController::class, 'kembalikan'])->name('penjualan.kembalikan')->middleware('can:order.kembalikan');
    Route::post('/penjualan/{order}/tandai-pengganti', [PenjualanController::class, 'tandaiPengganti'])->name('penjualan.tandai-pengganti')->middleware('can:order.batalkan');
    Route::get('/penjualan/{order}/edit', [PenjualanController::class, 'edit'])->name('penjualan.edit');
    Route::put('/penjualan/{order}', [PenjualanController::class, 'update'])->name('penjualan.update');
    Route::delete('/penjualan/{order}', [PenjualanController::class, 'destroy'])->name('penjualan.destroy');

    // ===== PELANGGAN (semua role) =====
    Route::resource('pelanggan', PelangganController::class);

    // ===== MASTER JENIS OLAHAN (Owner & Admin Pusat) =====
    // 4.43: controller memeriksa jenis-olahan.view / jenis-olahan.manage (dulu role:owner,admin_pusat)
    Route::resource('master/jenis-olahan', JenisOlahanController::class)
        ->parameters(['jenis-olahan' => 'jenis_olahan'])
        ->except(['show'])
        ->names('master.jenis-olahan')
        ->middleware('can:jenis-olahan.view');

    // ===== MASTER RESEP BUMBU STANDAR (permission-based, bukan role hardcode) =====
    Route::prefix('master/resep-bumbu')->name('master.resep-bumbu.')->group(function () {
        Route::get('/', [MasterResepBumbuController::class, 'index'])->name('index')->middleware('can:master.resep_bumbu.view');
        Route::get('/create', [MasterResepBumbuController::class, 'create'])->name('create')->middleware('can:master.resep_bumbu.create');
        Route::post('/', [MasterResepBumbuController::class, 'store'])->name('store')->middleware('can:master.resep_bumbu.create');
        Route::get('/{resepBumbu}/edit', [MasterResepBumbuController::class, 'edit'])->name('edit')->middleware('can:master.resep_bumbu.edit');
        Route::put('/{resepBumbu}', [MasterResepBumbuController::class, 'update'])->name('update')->middleware('can:master.resep_bumbu.edit');
        Route::delete('/{resepBumbu}', [MasterResepBumbuController::class, 'destroy'])->name('destroy')->middleware('can:master.resep_bumbu.delete');
        Route::post('/{resepBumbu}/items', [MasterResepBumbuController::class, 'storeItem'])->name('items.store')->middleware('can:master.resep_bumbu.edit');
        Route::delete('/{resepBumbu}/items/{resepBumbuItem}', [MasterResepBumbuController::class, 'destroyItem'])->name('items.destroy')->middleware('can:master.resep_bumbu.edit');
    });

    // ===== MASTER PROGRAM LOYALTY (Fase 1: tracking otomatis kg giling) =====
    Route::resource('loyalty-program', LoyaltyProgramController::class)->except(['destroy'])->middleware('can:loyalty.view');
    Route::post('/loyalty-program/{loyaltyProgram}/tandai-hadiah', [LoyaltyProgramController::class, 'tandaiHadiah'])
        ->name('loyalty-program.tandai-hadiah')->middleware('can:loyalty.manage');

    // ===== PROGRAM LOYALTY Fase 2 (event-based: klaim manual + bukti) =====
    Route::prefix('loyalty-klaim')->name('loyalty-klaim.')->group(function () {
        Route::get('/', [LoyaltyKlaimController::class, 'index'])->name('index')->middleware('can:loyalty.klaim.view');
        Route::get('/create', [LoyaltyKlaimController::class, 'create'])->name('create')->middleware('can:loyalty.klaim.buat');
        Route::post('/', [LoyaltyKlaimController::class, 'store'])->name('store')->middleware('can:loyalty.klaim.buat');
        Route::post('/{loyaltyKlaim}/approve', [LoyaltyKlaimController::class, 'approve'])->name('approve')->middleware('can:loyalty.klaim.approve');
        Route::post('/{loyaltyKlaim}/reject', [LoyaltyKlaimController::class, 'reject'])->name('reject')->middleware('can:loyalty.klaim.reject');
        Route::post('/{loyaltyKlaim}/issued', [LoyaltyKlaimController::class, 'markIssued'])->name('issued')->middleware('can:loyalty.klaim.issued');
    });

    // ===== HR / SDM =====

    // Karyawan — permission-driven (semua role dapat karyawan.view, write hanya owner/admin_pusat)
    Route::resource('karyawan', KaryawanController::class);

    // Shift — owner & admin_pusat saja
    Route::middleware('can:kelola_shift')->group(function () { // 4.43 (dulu role:owner,admin_pusat)
        Route::resource('shift', ShiftController::class);
        Route::patch('/shift/{shift}/toggle-aktif', [ShiftController::class, 'toggleAktif'])->name('shift.toggle-aktif');
    });

    // Absensi Saya & Scan — semua role yang sudah login
    Route::get('/absensi-saya', [AbsensiSayaController::class, 'index'])->name('absensi-saya.index');

    // Absensi Manual — manajer_cabang, admin_gudang, owner, admin_pusat
    // 4.43: ikut centangan Kelola Absensi / Dashboard Absensi (dulu role:owner,admin_pusat,manajer_cabang,admin_gudang)
    Route::middleware('can:absensi.manage')->group(function () {
        Route::get('/absensi', [AbsensiController::class, 'index'])->name('absensi.index');
        Route::post('/absensi', [AbsensiController::class, 'store'])->name('absensi.store');
        Route::get('/absensi/rekap', [AbsensiController::class, 'rekap'])->name('absensi.rekap');
        Route::get('/absensi/{absensi}/edit', [AbsensiController::class, 'edit'])->name('absensi.edit');
        Route::put('/absensi/{absensi}', [AbsensiController::class, 'update'])->name('absensi.update');
        // Log Absensi Wajah
        Route::get('/face-attendance/today', [FaceAttendanceController::class, 'today'])->name('face-attendance.today');
        Route::get('/face-attendance/log', [FaceAttendanceController::class, 'log'])->name('face-attendance.log');
    });
    Route::get('/dashboard/absensi', [DashboardAbsensiController::class, 'index'])->name('dashboard.absensi')->middleware('can:dashboard_absensi');

    // Hapus absensi & log face — hanya owner & admin_pusat
    // 4.43: ikut centangan Hapus Absensi / Hapus Log Absensi Wajah (dulu role:owner,admin_pusat)
    Route::delete('/absensi/{absensi}', [AbsensiController::class, 'destroy'])->name('absensi.destroy')->middleware('can:hapus_absensi');
    Route::delete('/face-attendance/log/{faceAttendance}', [FaceAttendanceController::class, 'destroyLog'])->name('face-attendance.log.destroy')->middleware('can:hapus_log_absensi');

    // Setup Device Absensi — owner, admin_pusat, manajer_cabang
    Route::middleware('can:absensi.device') // 4.43 (dulu role:owner,admin_pusat,manajer_cabang)
        ->prefix('absen-device')
        ->name('absen-device.')
        ->group(function () {
            Route::get('/',                        [AbsenDeviceController::class, 'index'])->name('index');
            Route::post('/',                       [AbsenDeviceController::class, 'store'])->name('store');
            Route::patch('/{device}/toggle-aktif', [AbsenDeviceController::class, 'toggleAktif'])->name('toggle-aktif');
            Route::patch('/{device}/reset-token',  [AbsenDeviceController::class, 'resetToken'])->name('reset-token');
            Route::delete('/{device}',             [AbsenDeviceController::class, 'destroy'])->name('destroy');
            Route::get('/{device}/scan-url',       [AbsenDeviceController::class, 'scanUrl'])->name('scan-url');
        });

    // Hari Libur — owner saja
    Route::middleware('can:kelola_hari_libur')->group(function () { // 4.43 (dulu khusus Owner)
        Route::resource('hari-libur', HariLiburController::class)->parameters(['hari-libur' => 'hariLibur']);
    });

    // ===== FACE RECOGNITION =====

    // Scan absensi wajah — semua role (setelah login)
    Route::get('/absen/scan', [FaceAttendanceController::class, 'scan'])->name('face-attendance.scan');
    Route::post('/face-attendance/proses', [FaceAttendanceController::class, 'proses'])->name('face-attendance.proses');
    Route::get('/api/face-data/cabang', [FaceRegistrationController::class, 'faceDataCabang'])->name('api.face-data.cabang');

    // Registrasi Wajah (Admin/Manajer)
    Route::middleware('can:registrasi_wajah')->group(function () { // 4.43 (dulu role:owner,admin_pusat,manajer_cabang,admin_gudang)
        Route::get('/face-registration', [FaceRegistrationController::class, 'index'])->name('face-registration.index');
        Route::get('/face-registration/{karyawan}/register', [FaceRegistrationController::class, 'register'])->name('face-registration.register');
        Route::post('/face-registration/{karyawan}/store', [FaceRegistrationController::class, 'store'])->name('face-registration.store');
        Route::delete('/face-registration/{karyawan}/reset', [FaceRegistrationController::class, 'reset'])->name('face-registration.reset');
    });

    // Penggajian — owner, admin_pusat, manajer_cabang
    // 4.43: lihat = penggajian.view, ubah = penggajian.manage (dulu role:owner,admin_pusat,manajer_cabang)
    Route::middleware('can:penggajian.view')->group(function () {
        Route::get('/penggajian', [PenggajianController::class, 'index'])->name('penggajian.index');
    });
    Route::middleware('can:penggajian.manage')->group(function () {
        Route::get('/penggajian/generate', [PenggajianController::class, 'generate'])->name('penggajian.generate');
        Route::post('/penggajian/generate', [PenggajianController::class, 'prosesGenerate'])->name('penggajian.proses-generate');
        Route::get('/penggajian/create', [PenggajianController::class, 'createSingle'])->name('penggajian.create');
        Route::get('/penggajian/rekap-absensi', [PenggajianController::class, 'rekapAbsensiAjax'])->name('penggajian.rekap-absensi');
        Route::post('/penggajian', [PenggajianController::class, 'storeSingle'])->name('penggajian.store');
    });
    Route::middleware('can:penggajian.view')->group(function () {
        Route::get('/penggajian/{penggajian}', [PenggajianController::class, 'show'])->name('penggajian.show');
        Route::get('/penggajian/{penggajian}/cetak', [PenggajianController::class, 'cetak'])->name('penggajian.cetak');
    });
    Route::middleware('can:penggajian.manage')->group(function () {
        Route::get('/penggajian/{penggajian}/edit', [PenggajianController::class, 'edit'])->name('penggajian.edit');
        Route::put('/penggajian/{penggajian}', [PenggajianController::class, 'update'])->name('penggajian.update');
        Route::post('/penggajian/{penggajian}/approve', [PenggajianController::class, 'approve'])->name('penggajian.approve');
        Route::post('/penggajian/{penggajian}/bayar', [PenggajianController::class, 'bayar'])->name('penggajian.bayar');
        Route::delete('/penggajian/{penggajian}', [PenggajianController::class, 'destroy'])->name('penggajian.destroy');
    });

    // Cuti & Izin — semua role
    Route::resource('cuti', CutiController::class)->except(['show']);
    Route::post('/cuti/{cuti}/approve', [CutiController::class, 'approve'])->name('cuti.approve');
    Route::post('/cuti/{cuti}/tolak', [CutiController::class, 'tolak'])->name('cuti.tolak');

    // Evaluasi 360° — semua role (masing-masing hanya bisa isi penilaian yang ditugaskan)
    Route::get('/evaluasi', [EvaluationController::class, 'periodIndex'])->name('evaluasi.periods')->middleware('can:evaluasi.view');
    Route::get('/evaluasi/saya', [EvaluationController::class, 'myReviews'])->name('evaluasi.my-reviews');
    // 4.43: lihat = evaluasi.view, kelola = evaluasi.manage (dulu role:owner,admin_pusat,manajer_cabang)
    Route::middleware('can:evaluasi.view')->group(function () {
        Route::get('/evaluasi/{period}/ranking', [EvaluationController::class, 'ranking'])->name('evaluasi.ranking');
        Route::get('/evaluasi/{period}/detail', [EvaluationController::class, 'periodDetail'])->name('evaluasi.period-detail');
    });
    Route::middleware('can:evaluasi.manage')->group(function () {
        Route::get('/evaluasi/create', [EvaluationController::class, 'periodCreate'])->name('evaluasi.period-create');
        Route::post('/evaluasi', [EvaluationController::class, 'periodStore'])->name('evaluasi.period-store');
        Route::post('/evaluasi/{period}/open', [EvaluationController::class, 'periodOpen'])->name('evaluasi.period-open');
        Route::post('/evaluasi/{period}/close', [EvaluationController::class, 'periodClose'])->name('evaluasi.period-close');
        Route::post('/evaluasi/{period}/finalize', [EvaluationController::class, 'periodFinalize'])->name('evaluasi.period-finalize');
        Route::post('/evaluasi/{evaluation}/assign-rekan', [EvaluationController::class, 'assignRekanKerja'])->name('evaluasi.assign-rekan');
    });
    Route::get('/evaluasi/{evaluation}/result', [EvaluationController::class, 'result'])->name('evaluasi.result');
    Route::get('/evaluasi/{evaluation}/isi', [EvaluationController::class, 'formPenilaian'])->name('evaluasi.form-penilaian');
    Route::post('/evaluasi/{evaluation}/isi', [EvaluationController::class, 'submitPenilaian'])->name('evaluasi.submit-penilaian');
    Route::get('/evaluasi/history/{karyawan}', [EvaluationController::class, 'history'])->name('evaluasi.history');

    // ===== KEUANGAN =====
    Route::get('/keuangan/dashboard', [KeuanganDashboardController::class, 'index'])->name('keuangan.dashboard');
    Route::get('/keuangan/dashboard/data', [KeuanganDashboardController::class, 'data'])->name('keuangan.dashboard.data');
    Route::get('/keuangan', [KeuanganController::class, 'index'])->name('keuangan.index');
    Route::get('/keuangan/export', [KeuanganController::class, 'export'])->name('keuangan.export');
    Route::get('/keuangan/create', [KeuanganController::class, 'create'])->name('keuangan.create');
    Route::get('/keuangan/po-pending-list', [KeuanganController::class, 'poPendingList'])->name('keuangan.po-pending-list');
    Route::post('/keuangan', [KeuanganController::class, 'store'])->name('keuangan.store');
    Route::get('/keuangan/kas', [KeuanganController::class, 'kas'])->name('keuangan.kas');
    Route::post('/keuangan/kas', [KeuanganController::class, 'storeKas'])->name('keuangan.kas.store');
    Route::put('/keuangan/kas/{kas}', [KeuanganController::class, 'updateKas'])->name('keuangan.kas.update');
    Route::delete('/keuangan/kas/{kas}', [KeuanganController::class, 'destroyKas'])->name('keuangan.kas.destroy');
    // Cleanup Tool B3 — sinkron saldo_sekarang dari riwayat transaksi (Owner-only)
    Route::get('/keuangan/kas/{kas}/preview-sinkron', [KeuanganController::class, 'previewSinkronSaldo'])
        ->name('keuangan.kas.preview-sinkron')->middleware('can:kas.sinkron_saldo.action');
    Route::post('/keuangan/kas/{kas}/sinkron-saldo', [KeuanganController::class, 'sinkronSaldo'])
        ->name('keuangan.kas.sinkron-saldo')->middleware('can:kas.sinkron_saldo.action');
    Route::post('/kas/log-buka-laci', [KeuanganController::class, 'logBukaLaci'])->name('kas.log-buka-laci')->middleware('can:kas.buka_laci');
    Route::get('/keuangan/laporan', [KeuanganController::class, 'laporan'])->name('keuangan.laporan');
    // Detail read-only — dipakai link "Lihat Transaksi" dari Detail PO utk
    // transaksi yang SUDAH ter-link (referensi_type terisi, tidak bisa lewat
    // edit() yang selalu menolak transaksi ber-referensi).
    Route::get('/keuangan/{transaksi}/detail', [KeuanganController::class, 'show'])->name('keuangan.show');
    Route::get('/keuangan/{transaksi}/edit', [KeuanganController::class, 'edit'])->name('keuangan.edit');
    Route::put('/keuangan/{transaksi}', [KeuanganController::class, 'update'])->name('keuangan.update');
    Route::delete('/keuangan/{transaksi}', [KeuanganController::class, 'destroy'])->name('keuangan.destroy');
    // Cleanup Tool B2 — assign Kas Sumber ke transaksi lama kas_id NULL (Owner-only)
    Route::post('/keuangan/{transaksi}/assign-kas', [KeuanganController::class, 'assignKas'])
        ->name('keuangan.assign-kas')->middleware('can:transaksi.assign_kas.action');

    // ===== TRANSFER ANTAR KAS (mutasi dana dalam 1 cabang, mis. Tunai <-> Bank) =====
    // BEDA dari Transfer/Perpindahan Dana (setoran.* di atas) yang ANTAR CABANG
    // + ada workflow approval — ini INSTAN, sesama cabang saja.
    Route::get('/keuangan/transfer-antar-kas', [TransferAntarKasController::class, 'index'])->name('transfer-antar-kas.index');
    Route::get('/keuangan/transfer-antar-kas/create', [TransferAntarKasController::class, 'create'])->name('transfer-antar-kas.create');
    Route::post('/keuangan/transfer-antar-kas', [TransferAntarKasController::class, 'store'])->name('transfer-antar-kas.store');
    Route::get('/keuangan/transfer-antar-kas/{transaksi}', [TransferAntarKasController::class, 'show'])->name('transfer-antar-kas.show');
    Route::delete('/keuangan/transfer-antar-kas/{transaksi}', [TransferAntarKasController::class, 'destroy'])->name('transfer-antar-kas.destroy');

    // ===== CLOSING / TUTUP BUKU (harian/mingguan/bulanan, per cabang & keseluruhan) =====
    Route::get('/keuangan/closing', [\App\Http\Controllers\ClosingController::class, 'index'])
        ->name('closing.index')->middleware('can:closing.view');
    Route::get('/keuangan/closing/riwayat', [\App\Http\Controllers\ClosingController::class, 'riwayat'])
        ->name('closing.riwayat')->middleware('can:closing.view');
    Route::get('/keuangan/closing/export', [\App\Http\Controllers\ClosingController::class, 'export'])
        ->name('closing.export')->middleware('can:closing.export');
    Route::get('/keuangan/closing/{closing}/export', [\App\Http\Controllers\ClosingController::class, 'exportArsip'])
        ->whereNumber('closing')->name('closing.show.export')->middleware('can:closing.export');
    Route::post('/keuangan/closing/harian', [\App\Http\Controllers\ClosingController::class, 'storeHarian'])
        ->name('closing.harian.store')->middleware('can:closing.harian');
    Route::post('/keuangan/closing/bulanan', [\App\Http\Controllers\ClosingController::class, 'storeBulanan'])
        ->name('closing.bulanan.store')->middleware('can:closing.bulanan');
    Route::get('/keuangan/closing/{closing}', [\App\Http\Controllers\ClosingController::class, 'show'])
        ->whereNumber('closing')->name('closing.show')->middleware('can:closing.view');
    Route::post('/keuangan/closing/{closing}/buka', [\App\Http\Controllers\ClosingController::class, 'buka'])
        ->whereNumber('closing')->name('closing.buka')->middleware('can:closing.buka_kunci');

    // ===== KATEGORI TRANSAKSI =====
    Route::resource('kategori-transaksi', KategoriTransaksiController::class)->except(['show']);
    Route::get('/kategori-transaksi-untuk-tipe', [KategoriTransaksiController::class, 'forTipe'])->name('kategori-transaksi.for-tipe');

    // ===== CHART OF ACCOUNTS (Fase 1 Akuntansi) — Owner-only default =====
    Route::get('/coa', [ChartOfAccountController::class, 'index'])->name('coa.index');

    // ===== TRANSFER / PERPINDAHAN DANA (rename dari "Setoran ke Pusat") =====
    // URL pindah ke /transfer-dana, TAPI route NAME tetap 'setoran.*' — supaya
    // seluruh route()/redirect()->route() existing di controller & view
    // (resources/views/setoran/*.blade.php) tetap jalan tanpa satu baris pun
    // diubah (nama route TIDAK terikat ke path URL-nya). Route dengan nama
    // 'transfer-dana.*' TIDAK dibuat sebagai duplikat URI yang sama — Laravel
    // RouteCollection meng-key route by [method][URI], jadi 2 route dengan
    // method+URI identik akan saling menimpa (nama pertama hilang total dari
    // route lookup) — makanya cukup 1 nama per URI, dan itu WAJIB 'setoran.*'
    // supaya kompatibel dengan kode existing. SetoranController class TIDAK
    // di-rename (risk regresi terlalu tinggi).
    Route::get('/transfer-dana', [SetoranController::class, 'index'])->name('setoran.index');
    Route::get('/transfer-dana/create', [SetoranController::class, 'create'])->name('setoran.create');
    Route::post('/transfer-dana', [SetoranController::class, 'store'])->name('setoran.store');
    Route::get('/transfer-dana-kas-tujuan', [SetoranController::class, 'getKasTujuan'])->name('setoran.kas-tujuan');
    Route::get('/transfer-dana/kas-summary/{kas}/{tanggal}', [SetoranController::class, 'getKasSummary'])->name('setoran.kas-summary');
    Route::get('/transfer-dana/{setoran}', [SetoranController::class, 'show'])->name('setoran.show');
    Route::delete('/transfer-dana/{setoran}', [SetoranController::class, 'destroy'])->name('setoran.destroy');
    Route::post('/transfer-dana/{setoran}/terima', [SetoranController::class, 'terima'])->name('setoran.terima');
    Route::post('/transfer-dana/{setoran}/tolak', [SetoranController::class, 'tolak'])->name('setoran.tolak');
    Route::post('/transfer-dana/{setoran}/batal', [SetoranController::class, 'batal'])->name('setoran.batal');

    // ===== Tahap 5 D'mentai — SETORAN KASIR (rekonsiliasi harian cabang -> HO) =====
    // BEDA dari 'setoran.*' (Transfer Dana antar cabang, generik, manual) di
    // atas — ini auto-hitung dari order_payments + approval workflow HO.
    Route::prefix('setoran-kasir')->name('setoran-kasir.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SetoranKasirController::class, 'index'])->name('index')->middleware('can:setoran_kasir.view');
        Route::get('/create', [\App\Http\Controllers\SetoranKasirController::class, 'create'])->name('create')->middleware('can:setoran_kasir.create');
        Route::post('/', [\App\Http\Controllers\SetoranKasirController::class, 'store'])->name('store')->middleware('can:setoran_kasir.create');
        Route::get('/{setoranKasir}', [\App\Http\Controllers\SetoranKasirController::class, 'show'])->name('show')->middleware('can:setoran_kasir.view');
        Route::post('/{setoranKasir}/approve', [\App\Http\Controllers\SetoranKasirController::class, 'approve'])->name('approve')->middleware('can:setoran_kasir.approve');
        Route::post('/{setoranKasir}/reject', [\App\Http\Controllers\SetoranKasirController::class, 'reject'])->name('reject')->middleware('can:setoran_kasir.reject');
    });

    // Backward compat: bookmark/link lama ke /setoran* redirect ke /transfer-dana*
    // SENGAJA Route::get() (bukan Route::redirect(), yang default catch SEMUA
    // method via Route::any()) — bookmark/link lama SELALU GET, tidak pernah
    // POST/PUT/DELETE. Kalau dibiarkan catch-all-method, redirect ini
    // berpotensi menelan submit form dari menu lain kalau ada bentrok pola
    // matching di layer lain (mis. proxy/rewrite production) sebelum sampai
    // ke controller tujuan yang benar.
    Route::get('/setoran', fn () => redirect('/transfer-dana'));
    Route::get('/setoran/{any}', fn (string $any) => redirect('/transfer-dana/' . $any))->where('any', '.*');

    // ===== ASET & PENYUSUTAN =====
    Route::get('/aset/export', [AssetController::class, 'export'])->name('aset.export');
    Route::resource('aset', AssetController::class)->parameters(['aset' => 'asset']);
    Route::post('/aset/{asset}/hitung-penyusutan', [AssetController::class, 'hitungPenyusutan'])->name('aset.hitung-penyusutan');
    Route::post('/aset/generate-depresiasi-bulan-ini', [AssetController::class, 'generateDepresiasiBulanIni'])->name('aset.generate-depresiasi');
    Route::post('/aset/{asset}/maintenance', [AssetController::class, 'tambahMaintenance'])->name('aset.maintenance');
    Route::post('/aset/{asset}/disposal', [AssetController::class, 'disposal'])->name('aset.disposal');

    // ===== LAPORAN =====
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::get('/penjualan', [LaporanPenjualanController::class, 'index'])->name('penjualan');
        Route::get('/stok', [LaporanStokController::class, 'index'])->name('stok');
        Route::get('/stok/pergerakan', [LaporanStokController::class, 'pergerakan'])->name('stok.pergerakan');
        Route::get('/stok/minimum', [LaporanStokController::class, 'minimum'])->name('stok.minimum');
        Route::get('/keuangan/laba-rugi', [LaporanKeuanganController::class, 'labaRugi'])->name('keuangan.laba-rugi');
        Route::get('/keuangan/arus-kas', [LaporanKeuanganController::class, 'arusKas'])->name('keuangan.arus-kas');
        Route::get('/keuangan/harian', [LaporanKeuanganController::class, 'harian'])->name('keuangan.harian');
        Route::get('/hr/absensi', [LaporanHRController::class, 'absensi'])->name('hr.absensi');
        Route::get('/hr/penggajian', [LaporanHRController::class, 'penggajian'])->name('hr.penggajian');
        Route::get('/hr/evaluasi', [LaporanHRController::class, 'evaluasi'])->name('hr.evaluasi');
        Route::get('/absensi', [LaporanAbsensiController::class, 'index'])->name('absensi')->middleware('can:laporan_absensi');
        Route::get('/absensi/export/excel', [LaporanAbsensiController::class, 'exportExcel'])->name('absensi.excel')->middleware('can:laporan_absensi');
        Route::get('/absensi/export/pdf', [LaporanAbsensiController::class, 'exportPdf'])->name('absensi.pdf')->middleware('can:laporan_absensi');
        Route::get('/aset', [LaporanAsetController::class, 'index'])->name('aset');
        Route::get('/aset/penyusutan', [LaporanAsetController::class, 'penyusutan'])->name('aset.penyusutan');
        Route::get('/aset/maintenance', [LaporanAsetController::class, 'maintenance'])->name('aset.maintenance');
        Route::get('/bep', [LaporanBepController::class, 'index'])->name('bep');
        Route::get('/bep/per-cabang', [LaporanBepController::class, 'perCabang'])->name('bep.per-cabang');
        // Bug8 FIX: route proyeksi dihapus (method dihapus dari LaporanBepController)
        // SP1C — Laporan Keuangan Baru
        Route::get('/setoran', [LaporanSetoranController::class, 'index'])->name('setoran');
        Route::get('/kategori', [LaporanKategoriController::class, 'index'])->name('kategori');
        Route::get('/audit-bukti', [LaporanAuditBuktiController::class, 'index'])->name('audit-bukti');
        Route::get('/saldo-kas', [LaporanSaldoKasController::class, 'index'])->name('saldo-kas');
        Route::get('/cabang-vs-cabang', [LaporanCabangController::class, 'index'])->name('cabang-vs-cabang');

        // Setoran Harian Konsolidasi — konsolidasi pemasukan/pengeluaran/net 1 cabang
        // per hari untuk kebutuhan setoran ke pusat (read-only, permission terpisah)
        Route::get('/setoran-harian', [SetoranHarianController::class, 'index'])
            ->name('setoran-harian.index')
            ->middleware('can:laporan.setoran_harian.view');
        Route::get('/setoran-harian/print', [SetoranHarianController::class, 'print'])
            ->name('setoran-harian.print')
            ->middleware('can:laporan.setoran_harian.print');
        Route::get('/setoran-harian/export', [SetoranHarianController::class, 'export'])
            ->name('setoran-harian.export')
            ->middleware('can:laporan.setoran_harian.export');

        // Konsumsi Bahan Baku — agregasi qty + HPP dari order_items per periode
        // (read-only, permission terpisah, tidak menyentuh laporan lain)
        Route::get('/konsumsi-bahan', [LaporanKonsumsiBahanController::class, 'index'])
            ->name('konsumsi-bahan.index')
            ->middleware('can:laporan.konsumsi_bahan.view');
        Route::get('/konsumsi-bahan/print', [LaporanKonsumsiBahanController::class, 'print'])
            ->name('konsumsi-bahan.print')
            ->middleware('can:laporan.konsumsi_bahan.print');
        Route::get('/konsumsi-bahan/export', [LaporanKonsumsiBahanController::class, 'export'])
            ->name('konsumsi-bahan.export')
            ->middleware('can:laporan.konsumsi_bahan.export');

        // Laba Rugi — analisis gross profit (omzet vs HPP) per item/kategori/
        // jenis olahan/order (read-only, permission terpisah)
        Route::get('/laba-rugi', [LaporanLabaRugiController::class, 'index'])
            ->name('laba-rugi.index')
            ->middleware('can:laporan.laba_rugi.view');
        Route::get('/laba-rugi/print', [LaporanLabaRugiController::class, 'print'])
            ->name('laba-rugi.print')
            ->middleware('can:laporan.laba_rugi.print');
        Route::get('/laba-rugi/export', [LaporanLabaRugiController::class, 'export'])
            ->name('laba-rugi.export')
            ->middleware('can:laporan.laba_rugi.export');

        // FASE 2 Akuntansi — Neraca formal SAK ETAP (menu BARU, terpisah dari
        // Laba Rugi existing di atas). Permission-nya sengaja Owner-only default.
        Route::get('/neraca', [LaporanNeracaController::class, 'index'])
            ->name('neraca.index')
            ->middleware('can:laporan.neraca.view');
        Route::get('/neraca/export', [LaporanNeracaController::class, 'export'])
            ->name('neraca.export')
            ->middleware('can:laporan.neraca.export');
        Route::get('/neraca/export-excel', [LaporanNeracaController::class, 'exportExcel'])
            ->name('neraca.export-excel')
            ->middleware('can:laporan.neraca.export');

        // FASE 2 Akuntansi — Laba Rugi Formal SAK ETAP (dikelompokkan per COA,
        // berbeda dari Laba Rugi existing di atas yang analisis gross profit).
        Route::get('/laba-rugi-formal', [LaporanLabaRugiFormalController::class, 'index'])
            ->name('laba-rugi-formal.index')
            ->middleware('can:laporan.laba_rugi_formal.view');
        Route::get('/laba-rugi-formal/export', [LaporanLabaRugiFormalController::class, 'export'])
            ->name('laba-rugi-formal.export')
            ->middleware('can:laporan.laba_rugi_formal.export');
        Route::get('/laba-rugi-formal/export-excel', [LaporanLabaRugiFormalController::class, 'exportExcel'])
            ->name('laba-rugi-formal.export-excel')
            ->middleware('can:laporan.laba_rugi_formal.export');

        // FASE 3 — BEP Otomatis (menu BARU, extend menu BEP existing di atas
        // yang butuh setup manual BepSetting/BepProduct). Hitung langsung
        // dari transaksi real, tanpa setup apapun.
        Route::get('/bep-otomatis', [LaporanBepOtomatisController::class, 'index'])
            ->name('bep-otomatis.index')
            ->middleware('can:laporan.bep_otomatis.view');
        Route::get('/bep-otomatis/export', [LaporanBepOtomatisController::class, 'export'])
            ->name('bep-otomatis.export')
            ->middleware('can:laporan.bep_otomatis.export');
        Route::get('/bep-otomatis/export-excel', [LaporanBepOtomatisController::class, 'exportExcel'])
            ->name('bep-otomatis.export-excel')
            ->middleware('can:laporan.bep_otomatis.export');

        // FASE 3 — Buku Besar (General Ledger) per akun COA, scope terbatas
        // (hanya akun Pendapatan/HPP/Beban — akun Aset/Kewajiban/Modal
        // datanya dari tabel lain, bukan transaksi_keuangans per baris).
        Route::get('/buku-besar', [BukuBesarController::class, 'index'])
            ->name('buku-besar.index')
            ->middleware('can:laporan.buku_besar.view');
        Route::get('/buku-besar/export', [BukuBesarController::class, 'export'])
            ->name('buku-besar.export')
            ->middleware('can:laporan.buku_besar.export');
        Route::get('/buku-besar/export-excel', [BukuBesarController::class, 'exportExcel'])
            ->name('buku-besar.export-excel')
            ->middleware('can:laporan.buku_besar.export');

        // Laporan Eksekutif Keuangan (Sesi A) — 6 halaman: Cover Overview,
        // Neraca, Laba Rugi, BEP, Arus Kas, Drill-Down & Verifikasi. Murni
        // compose dari service Fase 1-3 yang sudah ada (READ-ONLY).
        Route::get('/eksekutif', [LaporanEksekutifController::class, 'index'])
            ->name('eksekutif.index')
            ->middleware('can:laporan.eksekutif.view');
        Route::get('/eksekutif/preview', [LaporanEksekutifController::class, 'generate'])
            ->name('eksekutif.preview')
            ->middleware('can:laporan.eksekutif.view');
        Route::get('/eksekutif/export', [LaporanEksekutifController::class, 'export'])
            ->name('eksekutif.export')
            ->middleware('can:laporan.eksekutif.export');

        // Interactive Simulator BEP (Sesi B) — slider real-time, kalkulasi
        // client-side, nilai awal reuse BepOtomatisService/NeracaService.
        Route::get('/simulator-bep', [SimulatorBepController::class, 'index'])
            ->name('simulator-bep.index')
            ->middleware('can:laporan.simulator.view');
        Route::post('/simulator-bep/export-excel', [SimulatorBepController::class, 'exportExcel'])
            ->name('simulator-bep.export-excel')
            ->middleware('can:laporan.simulator.export');
        Route::post('/simulator-bep/export-pdf', [SimulatorBepController::class, 'exportPdf'])
            ->name('simulator-bep.export-pdf')
            ->middleware('can:laporan.simulator.export');

        // Analisa Jam Ramai (Peak Hours) — menu BARU, murni dari
        // orders.created_at (lihat JamRamaiService untuk alasan
        // transaksi_keuangans TIDAK dipakai sebagai sumber).
        Route::get('/jam-ramai', [LaporanJamRamaiController::class, 'index'])
            ->name('jam-ramai.index')
            ->middleware('can:laporan.jam_ramai.view');
        Route::get('/jam-ramai/export-excel', [LaporanJamRamaiController::class, 'exportExcel'])
            ->name('jam-ramai.export-excel')
            ->middleware('can:laporan.jam_ramai.export');
        Route::get('/jam-ramai/export-pdf', [LaporanJamRamaiController::class, 'exportPdf'])
            ->name('jam-ramai.export-pdf')
            ->middleware('can:laporan.jam_ramai.export');

        // Laporan Pemakaian Perlengkapan (Fase 5, Rule #66) — baca dari
        // pemakaian_perlengkapans (tabel BARU), bukan reuse konsumsi-bahan
        // (sumbernya order_items, perlengkapan tidak masuk order pelanggan).
        Route::get('/perlengkapan', [\App\Http\Controllers\LaporanPerlengkapanController::class, 'index'])
            ->name('perlengkapan.index')
            ->middleware('can:laporan.perlengkapan.view');

        // Tahap 6 D'mentai — Laporan Setoran Kasir (beda dari laporan.setoran
        // existing yang melaporkan Transfer Dana generik).
        Route::get('/setoran-kasir', [\App\Http\Controllers\LaporanSetoranKasirController::class, 'index'])
            ->name('setoran-kasir.index')
            ->middleware('can:laporan.setoran_kasir.view');
        Route::get('/setoran-kasir/export', [\App\Http\Controllers\LaporanSetoranKasirController::class, 'export'])
            ->name('setoran-kasir.export')
            ->middleware('can:laporan.setoran_kasir.export');
        Route::get('/perlengkapan/print', [\App\Http\Controllers\LaporanPerlengkapanController::class, 'print'])
            ->name('perlengkapan.print')
            ->middleware('can:laporan.perlengkapan.print');
        Route::get('/perlengkapan/export', [\App\Http\Controllers\LaporanPerlengkapanController::class, 'export'])
            ->name('perlengkapan.export')
            ->middleware('can:laporan.perlengkapan.export');
        Route::get('/perlengkapan/export-pdf', [\App\Http\Controllers\LaporanPerlengkapanController::class, 'exportPdf'])
            ->name('perlengkapan.export-pdf')
            ->middleware('can:laporan.perlengkapan.export');
    });

    // ===== NOTIFIKASI =====
    // Static routes HARUS sebelum wildcard route {id}
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifikasi.index');
    Route::get('/notifikasi/json/unread-count', [NotificationController::class, 'unreadCount'])->name('notifikasi.unread-count');
    Route::get('/notifikasi/json/latest', [NotificationController::class, 'latest'])->name('notifikasi.latest');
    Route::post('/notifikasi/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifikasi.read-all');
    // Wildcard routes setelah static routes
    Route::get('/notifikasi/{id}/go', [NotificationController::class, 'go'])->name('notifikasi.go');
    Route::post('/notifikasi/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifikasi.read');
    Route::delete('/notifikasi/{id}', [NotificationController::class, 'destroy'])->name('notifikasi.destroy');

    // ===== AUDIT LOG, TRASH, BACKUP — Permission-based (abort_unless di controller) =====
    Route::group([], function () {
        // Audit Log
        Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
        Route::get('/audit-log/{id}', [AuditLogController::class, 'show'])->name('audit-log.show');

        // Trash (restore soft-deleted)
        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
        Route::get('/trash/{model}/{id}/detail', [TrashController::class, 'detail'])
            ->name('trash.detail')
            ->middleware('can:lihat_data_terhapus');
        Route::post('/trash/{model}/{id}/restore', [TrashController::class, 'restore'])->name('trash.restore');
        Route::delete('/trash/{model}/{id}/force', [TrashController::class, 'forceDestroy'])->name('trash.force-destroy');

        // Backup
        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('/backup/run', [BackupController::class, 'run'])->name('backup.run');
        Route::get('/backup/download/{filename}', [BackupController::class, 'download'])->name('backup.download')->where('filename', '.*');
        Route::delete('/backup/{filename}', [BackupController::class, 'destroy'])->name('backup.destroy')->where('filename', '.*');
    });

    // ===== PANDUAN PENGGUNA (semua user login) =====
    Route::get('/panduan', [PanduanController::class, 'index'])->name('panduan.index');
    Route::get('/panduan/{slug}', [PanduanController::class, 'show'])->name('panduan.show');

    // ===== ADMIN: PANDUAN =====
    // 4.43: ikut centangan Kelola Panduan & Tooltip (dulu role:owner,admin_pusat)
    Route::middleware('can:panduan.kelola')->prefix('admin/panduan')->name('admin.panduan.')->group(function () {
        Route::get('/',                        [AdminPanduanController::class, 'index'])->name('index');
        Route::get('/create',                  [AdminPanduanController::class, 'create'])->name('create');
        Route::post('/',                       [AdminPanduanController::class, 'store'])->name('store');
        Route::post('/preview',                function () {
            $konten = request()->input('konten', '');
            try {
                $converter = new \League\CommonMark\GithubFlavoredMarkdownConverter([
                    'html_input' => 'strip', 'allow_unsafe_links' => false,
                ]);
                $html = $converter->convert($konten)->getContent();
            } catch (\Throwable) {
                $html = e($konten);
            }
            return response()->json(['html' => $html]);
        })->name('preview');
        Route::get('/{panduan}/edit',          [AdminPanduanController::class, 'edit'])->name('edit');
        Route::put('/{panduan}',               [AdminPanduanController::class, 'update'])->name('update');
        Route::delete('/{panduan}',            [AdminPanduanController::class, 'destroy'])->name('destroy');
        Route::patch('/{panduan}/toggle-aktif',[AdminPanduanController::class, 'toggleAktif'])->name('toggle-aktif');
    });

    // ===== ADMIN: TOOLTIP HELPER =====
    Route::middleware('can:panduan.kelola')->prefix('admin/tooltips')->name('admin.tooltips.')->group(function () {
        Route::get('/',                         [AdminTooltipController::class, 'index'])->name('index');
        Route::get('/create',                   [AdminTooltipController::class, 'create'])->name('create');
        Route::post('/',                        [AdminTooltipController::class, 'store'])->name('store');
        Route::get('/{tooltip}/edit',           [AdminTooltipController::class, 'edit'])->name('edit');
        Route::put('/{tooltip}',                [AdminTooltipController::class, 'update'])->name('update');
        Route::delete('/{tooltip}',             [AdminTooltipController::class, 'destroy'])->name('destroy');
        Route::patch('/{tooltip}/toggle-aktif', [AdminTooltipController::class, 'toggleAktif'])->name('toggle-aktif');
    });

    // ===== CLEANUP ORPHAN (sekali pakai untuk Owner) =====
    Route::get('/admin/cleanup-orphan-cascade', function () {
        abort_unless(auth()->user()->can('restore_data_terhapus'), 403, 'Anda tidak memiliki akses untuk menjalankan cleanup ini.');

        $fixed = app(\App\Services\CascadeDeleteService::class)->cleanupOrphanSoftDeleted();

        if (empty($fixed)) {
            return response()->json(['message' => 'Tidak ada data orphan ditemukan. Semua sudah bersih.', 'fixed' => []]);
        }

        $total = array_sum($fixed);
        return response()->json([
            'message' => "Cleanup selesai. Total {$total} record orphan dipulihkan.",
            'fixed'   => $fixed,
        ]);
    })->name('admin.cleanup-orphan-cascade');

    // ===== RECURRING TRANSACTION =====
    Route::prefix('recurring')->name('recurring.')->group(function () {
        Route::get('/', [RecurringTransaksiController::class, 'index'])->name('index');
        Route::get('/create', [RecurringTransaksiController::class, 'create'])->name('create');
        Route::post('/', [RecurringTransaksiController::class, 'store'])->name('store');
        Route::get('/{recurring}/edit', [RecurringTransaksiController::class, 'edit'])->name('edit');
        Route::put('/{recurring}', [RecurringTransaksiController::class, 'update'])->name('update');
        Route::delete('/{recurring}', [RecurringTransaksiController::class, 'destroy'])->name('destroy');
        Route::post('/{recurring}/generate', [RecurringTransaksiController::class, 'generate'])->name('generate');
        Route::post('/{recurring}/toggle-active', [RecurringTransaksiController::class, 'toggleActive'])->name('toggle-active');
    });

    // ===== ANALISIS BEP =====
    Route::get('/bep', [BepController::class, 'index'])->name('bep.index');
    Route::get('/bep/setting', [BepController::class, 'setting'])->name('bep.setting');
    Route::post('/bep/setting', [BepController::class, 'storeSetting'])->name('bep.setting.store');
    Route::post('/bep/setting/{setting}/fixed-cost', [BepController::class, 'storeFixedCost'])->name('bep.fixed-cost.store');
    Route::delete('/bep/fixed-cost/{item}', [BepController::class, 'deleteFixedCost'])->name('bep.fixed-cost.delete');
    Route::post('/bep/setting/{setting}/product', [BepController::class, 'storeProduct'])->name('bep.product.store');
    Route::delete('/bep/product/{product}', [BepController::class, 'deleteProduct'])->name('bep.product.delete');
    Route::post('/bep/setting/{setting}/hitung-ulang', [BepController::class, 'hitungUlang'])->name('bep.hitung-ulang');
    Route::post('/bep/setting/{setting}/auto-fill', [BepController::class, 'autoFill'])->name('bep.auto-fill');
    // Bug7 FIX: route bep.laporan dihapus — laporan sekarang via laporan.bep (LaporanBepController)

    // ===== TEST PUSHER (hapus setelah koneksi dikonfirmasi) =====
    Route::get('/test-pusher',      fn () => view('test-pusher'))->name('test.pusher.show');
    Route::post('/test-pusher-fire', function () {
        try {
            event(new \App\Events\TestPusherEvent(
                message:  "Halo dari ERP D'mentai!",
                time:     now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i:s'),
                firedBy:  auth()->user()->name ?? 'Anonymous',
            ));
            return response()->json(['success' => true, 'message' => 'Event dikirim ke Pusher.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    })->name('test.pusher.fire');

});

require __DIR__.'/auth.php';
