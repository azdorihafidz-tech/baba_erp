<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\JenisOlahan;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\ResepBumbu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 2 - Master Data (2026-09-13): hapus PERMANEN data demo/seed warisan
 * Berkah Mulyo yang jelas tidak relevan untuk bisnis D'mentai (dimsum/gyoza),
 * supaya Item/ItemCategory/ResepBumbu/JenisOlahan bisa di-reseed bersih.
 *
 * Diverifikasi dulu sebelum ditulis: seluruh data di sini adalah data
 * SEED/DEMO (2 orders, 2 purchase_orders, 3 resep_bumbu, 17 items, 10
 * item_categories, 3 jenis_olahans) — BUKAN data produksi (data produksi
 * Berkah Mulyo ada di database `erp_berkahmulyo` terpisah, port 8000).
 *
 * Urutan hapus mengikuti arah FK constraint (child dulu via cascade
 * Eloquent, bukan raw DELETE manual) supaya tidak melanggar constraint:
 *   orders → order_items (CASCADE)
 *   purchase_orders → purchase_order_items (CASCADE)
 *   resep_bumbu → resep_bumbu_items (CASCADE)
 *   jenis_olahans (aman, resep_bumbu.jenis_olahan_id sudah SET NULL di atas)
 *   items (aman, order_items/purchase_order_items/resep_bumbu_items sudah
 *          kosong; stocks/stock_batches 0 baris di database ini)
 *   item_categories (aman, items.item_category_id SET NULL / sudah dihapus)
 *
 * SENGAJA TIDAK disentuh (dipertahankan sesuai keputusan Tahap 2):
 *   users, karyawans, role_permissions, permissions, panduan, tooltips,
 *   kategori_transaksis, chart_of_accounts, assets, kas, transaksi_keuangans,
 *   cabangs (di-rename di CabangSeeder, bukan dihapus),
 *   suppliers, pelanggans (dummy generik, tidak spesifik daging — dibiarkan).
 *
 * Idempotent: aman dijalankan ulang — kalau data sudah tidak ada, query count
 * di tiap tahap otomatis 0, tidak ada error.
 */
class CleanupBerkahMulyoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Order/PurchaseOrder/Item/ItemCategory pakai SoftDeletes — withTrashed()
            // supaya baris yang kebetulan sudah soft-deleted juga ikut terpurge,
            // forceDelete() untuk hard-delete permanen (bukan cuma set deleted_at).
            $ordersDeleted = Order::withTrashed()->count();
            Order::withTrashed()->forceDelete();
            $this->command?->info("  - orders dihapus: {$ordersDeleted}");

            $poDeleted = PurchaseOrder::withTrashed()->count();
            PurchaseOrder::withTrashed()->forceDelete();
            $this->command?->info("  - purchase_orders dihapus: {$poDeleted}");

            // ResepBumbu & JenisOlahan TIDAK pakai SoftDeletes (master lookup
            // sederhana, sudah dikonfirmasi sejak sesi Berkah Mulyo) — ->delete()
            // di sini sudah hard-delete langsung.
            $resepDeleted = ResepBumbu::query()->count();
            ResepBumbu::query()->delete();
            $this->command?->info("  - resep_bumbu dihapus: {$resepDeleted}");

            $jenisOlahanDeleted = JenisOlahan::query()->count();
            JenisOlahan::query()->delete();
            $this->command?->info("  - jenis_olahans dihapus: {$jenisOlahanDeleted}");

            $itemsDeleted = Item::withTrashed()->count();
            Item::withTrashed()->forceDelete();
            $this->command?->info("  - items dihapus: {$itemsDeleted}");

            $katDeleted = ItemCategory::withTrashed()->count();
            ItemCategory::withTrashed()->forceDelete();
            $this->command?->info("  - item_categories dihapus: {$katDeleted}");
        });

        $this->command?->info('Cleanup data Berkah Mulyo selesai.');
    }
}
