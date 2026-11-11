<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use App\Models\Stock;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Item;
use App\Models\User;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\Cuti;
use App\Models\Asset;
use App\Models\Cabang;

use App\Observers\StockObserver;
use App\Observers\StockRequestObserver;
use App\Observers\StockRequestItemObserver;
use App\Observers\StockTransferObserver;
use App\Observers\StockTransferItemObserver;
use App\Observers\ItemObserver;
use App\Observers\UserObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\PurchaseOrderItemObserver;
use App\Observers\OrderObserver;
use App\Observers\OrderItemObserver;
use App\Observers\SupplierObserver;
use App\Observers\KaryawanObserver;
use App\Observers\PenggajianObserver;
use App\Observers\CutiObserver;
use App\Observers\AssetObserver;
use App\Observers\CabangObserver;
use App\Observers\TransaksiKeuanganObserver;
use App\Observers\PengaturanUmumObserver;
use App\Models\PengaturanUmum;
use App\Models\TransaksiKeuangan;
use App\Models\RecurringTransaksi;
use App\Listeners\SendBellPusherSignal;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerStorageAwareUrlGenerator();

        // 4.44 Poin 3 — identitas usaha (nama/logo) dari Pengaturan Umum
        $this->app->singleton(\App\Support\Branding::class);
    }

    /**
     * Override URL::asset() supaya asset('storage/xxx') generate URL
     * 'asset/xxx' (bukan 'storage/xxx') — lihat penjelasan lengkap di
     * App\Support\StorageAwareUrlGenerator. Registrasi ini MEREPLIKASI
     * PERSIS setup 'url' bawaan Illuminate\Routing\RoutingServiceProvider
     * (session/key resolver utk signed URL, request+routes rebinding) di
     * instance BARU (subclass kita) — kalau ini tidak direplikasi, fitur
     * signed URL (mis. verifikasi email) akan diam-diam rusak.
     */
    private function registerStorageAwareUrlGenerator(): void
    {
        $this->app->extend('url', function ($urlGenerator, $app) {
            $generator = new \App\Support\StorageAwareUrlGenerator(
                $app['router']->getRoutes(),
                $urlGenerator->getRequest(),
                $app['config']['app.asset_url']
            );

            $generator->setSessionResolver(function () use ($app) {
                return $app['session'] ?? null;
            });

            $generator->setKeyResolver(function () use ($app) {
                $config = $app->make('config');
                return [$config->get('app.key'), ...($config->get('app.previous_keys') ?? [])];
            });

            $app->rebinding('request', function ($app, $request) use ($generator) {
                $generator->setRequest($request);
            });

            $app->rebinding('routes', function ($app, $routes) use ($generator) {
                $generator->setRoutes($routes);
            });

            return $generator;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gunakan Bootstrap 5 untuk pagination (bukan Tailwind default Laravel 12)
        Paginator::useBootstrapFive();

        // 4.44 — $brand di semua view (data dimuat saat pertama dipakai, bukan di sini)
        \Illuminate\Support\Facades\View::share('brand', $this->app->make(\App\Support\Branding::class));

        // @rupiah($amount) — tampilkan nominal Rupiah terformat, contoh: @rupiah($harga) → Rp 5.000.000
        Blade::directive('rupiah', function ($expression) {
            return "<?php echo 'Rp ' . number_format((int) round((float) ($expression ?? 0)), 0, ',', '.'); ?>";
        });

        // Owner bypass semua gate check
        Gate::before(function (User $user, string $ability) {
            if ($user->role?->value === 'owner') {
                return true;
            }
        });

        // Daftarkan semua permission dari DB sebagai Gate
        // Sehingga bisa pakai @can('stok.view') di Blade
        try {
            $permissionNames = Cache::remember(
                'all_permission_names', 3600,
                fn () => \App\Models\Permission::pluck('name')->toArray()
            );
            foreach ($permissionNames as $permName) {
                Gate::define($permName, function (User $user) use ($permName) {
                    return $user->hasPermission($permName);
                });
            }
        } catch (\Exception $e) {
            // Tabel belum ada saat migrasi pertama kali
        }

        Stock::observe(StockObserver::class);
        StockRequest::observe(StockRequestObserver::class);
        StockRequestItem::observe(StockRequestItemObserver::class);
        StockTransfer::observe(StockTransferObserver::class);
        StockTransferItem::observe(StockTransferItemObserver::class);
        Item::observe(ItemObserver::class);
        PengaturanUmum::observe(PengaturanUmumObserver::class);
        User::observe(UserObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        PurchaseOrderItem::observe(PurchaseOrderItemObserver::class);
        Order::observe(OrderObserver::class);
        OrderItem::observe(OrderItemObserver::class);
        Supplier::observe(SupplierObserver::class);
        Karyawan::observe(KaryawanObserver::class);
        Penggajian::observe(PenggajianObserver::class);
        Cuti::observe(CutiObserver::class);
        Asset::observe(AssetObserver::class);
        Cabang::observe(CabangObserver::class);
        TransaksiKeuangan::observe(TransaksiKeuanganObserver::class);

        // Pusher real-time: kirim signal ke bell lonceng setiap notifikasi database masuk
        Event::listen(NotificationSent::class, SendBellPusherSignal::class);

        // On-request recurring trigger: generate sekali per hari tanpa perlu cron
        $this->triggerRecurringIfNeeded();
    }

    private function triggerRecurringIfNeeded(): void
    {
        $cacheKey = 'recurring_run_' . now()->toDateString();
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->endOfDay());

        try {
            $recurrings = RecurringTransaksi::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->jatuhTempo()
                ->get();

            foreach ($recurrings as $r) {
                // Per item: 1 gagal (mis. hari ini sudah di-closing) tidak
                // menghentikan transaksi berulang lainnya.
                try {
                    DB::transaction(fn () => $r->generateTransaksi());
                } catch (\App\Exceptions\PeriodeTerkunciException $e) {
                    Log::info('Recurring #' . $r->id . ' dilewati: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::error('Recurring auto-generate failed: ' . $e->getMessage());
        }
    }
}
