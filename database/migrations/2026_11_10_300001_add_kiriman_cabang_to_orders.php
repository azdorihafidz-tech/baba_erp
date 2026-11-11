<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 4.49 Tahap B2 — Kirim ke Cabang (porting erp-coffeeshop 40bd55e/9fc2b31).
 * POS Gudang Pusat menjual Produk Jual ke cabang sendiri (customer_type =
 * internal) yang menunggu cabang menekan Terima. Selama menunggu
 * orders.status = 'proses' (enum lama yang belum pernah dipakai Dimsum) →
 * StatusOrder::bukanPenjualan() → semua laporan otomatis tidak menghitungnya.
 *
 * Dimsum belum punya customer_type/outlet_tujuan_id (coffeeshop dari Roastery),
 * jadi ditambah di sini. order_kiriman_items = rincian barang yang benar-benar
 * keluar dari stok gudang per baris order (bahan komposisi × qty) + modal FIFO.
 * Hanya kolom/tabel baru; order lama tidak tersentuh (semua kolom NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_type', 20)->nullable()->after('purchase_order_id'); // NULL = penjualan biasa, 'internal' = kiriman/retur antar cabang
            $table->foreignId('outlet_tujuan_id')->nullable()->after('customer_type')->constrained('cabangs')->nullOnDelete();
            $table->enum('status_kirim', ['menunggu', 'diterima', 'ditolak', 'dibatalkan'])->nullable()->after('outlet_tujuan_id');
            $table->timestamp('diterima_at')->nullable()->after('status_kirim');
            $table->foreignId('diterima_by')->nullable()->after('diterima_at')->constrained('users')->nullOnDelete();
            $table->string('alasan_tolak', 500)->nullable()->after('diterima_by');
            $table->index(['outlet_tujuan_id', 'status_kirim']);
        });

        Schema::create('order_kiriman_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();        // barang gudang yang dikirim
            $table->foreignId('item_tujuan_id')->nullable()->constrained('items')->nullOnDelete(); // padanan milik cabang tujuan (diisi saat Terima)
            $table->decimal('qty_kirim', 10, 3);
            $table->decimal('hpp_kirim', 15, 2)->default(0);     // modal FIFO gudang untuk qty_kirim
            $table->decimal('qty_terima', 10, 3)->nullable();
            $table->decimal('nilai_terima', 15, 2)->nullable();  // harga yang dibayar cabang untuk qty_terima
            $table->decimal('qty_retur', 10, 3)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_kiriman_items');

        // MySQL memakai index gabungan (outlet_tujuan_id, status_kirim) untuk FK outlet_tujuan_id
        // (temuan coffeeshop) — pasang index FK dulu supaya index gabungan bisa dihapus.
        if (! Schema::hasIndex('orders', 'orders_outlet_tujuan_id_foreign')) {
            Schema::table('orders', fn (Blueprint $table) => $table->index('outlet_tujuan_id', 'orders_outlet_tujuan_id_foreign'));
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['outlet_tujuan_id', 'status_kirim']);
            $table->dropConstrainedForeignId('diterima_by');
            $table->dropConstrainedForeignId('outlet_tujuan_id');
            $table->dropColumn(['customer_type', 'status_kirim', 'diterima_at', 'alasan_tolak']);
        });
    }
};
