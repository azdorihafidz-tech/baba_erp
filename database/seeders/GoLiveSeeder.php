<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\EvaluationAspect;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Seeder Go-Live D'mentai: hanya data referensi wajib + 1 Owner real + 1
 * Gudang Pusat placeholder. SENGAJA tidak memanggil CabangSeeder, UserSeeder,
 * NeracaSettingSeeder, ItemSeeder, ResepBumbuSeeder, StokSeeder, HRSeeder,
 * PembelianPenjualanSeeder, ProgramLoyaltySeeder, CleanupBerkahMulyoDataSeeder,
 * TestingBatch1SeederTemp (semua data dummy/dev). Idempotent.
 */
class GoLiveSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('golive.owner_email');
        $password = config('golive.owner_password');

        if (app()->environment('production')
            && ($email === config('golive.default_email') || $password === config('golive.default_password'))) {
            throw new \RuntimeException(
                'GOLIVE_OWNER_EMAIL / GOLIVE_OWNER_PASSWORD masih default. Isi kredensial real di .env production dulu.'
            );
        }

        // Urutan penting: semua permission dulu, RolePermission setelahnya.
        // Panduan: Pos -> Stub -> Konten (Stub me-rename slug 'pos' jadi 'penjualan').
        $this->call([
            PermissionSeeder::class,
            KeamananPermissionSeeder::class,
            AbsensiPermissionSeeder::class,
            AntrianPermissionSeeder::class,
            KategoriTransaksiSeeder::class,
            ChartOfAccountsSeeder::class,
            KategoriCoaBackfillSeeder::class,
            PerlengkapanKategoriCoaSeeder::class,
            JenisOlahanSeeder::class,
        ]);

        // EvaluationAspectSeeder pakai create() biasa (tidak idempotent).
        if (EvaluationAspect::count() === 0) {
            $this->call(EvaluationAspectSeeder::class);
        }

        $this->call([
            ItemCategorySeeder::class,
            AssetCategorySeeder::class,
            RolePermissionSeeder::class,
            HakAksesMenuSeeder::class,
            PanduanPosSeeder::class,
            PanduanStubSeeder::class,
            PanduanKontenSeeder::class,
            Sprint434PanduanUpdateSeeder::class,
            TooltipAdjustmentSeeder::class,
            TooltipKontenSeeder::class,
            ClosingPanduanSeeder::class,
            Perbaikan438PanduanSeeder::class,
            IdentitasUsahaSeeder::class,
            LabelProdukSeeder::class,
            LaporanPenjualanKasirSeeder::class, // 4.46 — izin + panduan Laporan di menu Penjualan
            PosUbahHargaSeeder::class,          // 4.47 — izin Ubah Harga Jual di POS + panduan POS
            WarnaAplikasiSeeder::class,         // 4.48 — panduan & tooltip Warna Aplikasi (setelah IdentitasUsahaSeeder)
            Porting449Seeder::class,           // 4.49 — porting coffeeshop: label gambar, POS HP, produksi, kiriman, retur (setelah LabelProdukSeeder)
        ]);

        $gudang = Cabang::updateOrCreate(
            ['kode_cabang' => 'HO-01'],
            [
                'nama_cabang' => "Gudang Pusat D'mentai",
                'alamat'      => 'Alamat kantor pusat',
                'telepon'     => '0000000000',
                'tipe'        => 'gudang_pusat',
                'is_active'   => true,
            ]
        );

        $owner = User::firstOrNew(['email' => $email]);
        $owner->fill([
            'name'      => config('golive.owner_name'),
            'password'  => $password,
            'role'      => 'owner',
            'is_active' => true,
        ]);
        $owner->forceFill(['email_verified_at' => $owner->email_verified_at ?? now()])->save();
        $owner->cabangs()->syncWithoutDetaching([$gudang->id => ['is_default' => true]]);

        Cache::forget('all_permission_names');

        $this->command?->info("Owner: {$email} | Gudang Pusat: {$gudang->kode_cabang}");
    }
}
