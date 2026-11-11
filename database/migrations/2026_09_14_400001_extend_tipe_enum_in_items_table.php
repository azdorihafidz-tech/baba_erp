<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 2.5 D'mentai — pisah konsep "Bahan Baku/Kemasan" vs "Produk Jual"
 * yang sebelumnya campur aduk di 1 kolom `tipe`. EXTEND enum (bukan replace)
 * supaya nilai lama tetap valid untuk backward-compat kalau ada kode lain
 * yang masih merujuknya — hanya 21 item dummy existing yang direklasifikasi
 * datanya, skema kolom tetap 1 tabel `items`.
 *
 * Mapping reklasifikasi (kode_item -> tipe baru):
 *  - PJ-* (10 produk dimsum/gyoza/minuman/frozen)  : produk_jadi -> produk_jual
 *  - BB-BHN-*, KM-KMS-*                            : tetap (bahan_baku/kemasan)
 *  - TB-TMB-001 (Garpu Plastik, gratis)            : lainnya -> tambahan_gratis
 *  - TB-TMB-002 (Saus Cabai Extra, berbayar)       : lainnya -> produk_tambahan
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE items MODIFY tipe ENUM(
            'bahan_baku','produk_jadi','kemasan','lainnya',
            'tambahan_gratis','produk_jual','produk_tambahan'
        ) NOT NULL DEFAULT 'bahan_baku'");

        DB::table('items')->where('tipe', 'produk_jadi')->update(['tipe' => 'produk_jual']);
        DB::table('items')->where('kode_item', 'TB-TMB-001')->update(['tipe' => 'tambahan_gratis']);
        DB::table('items')->where('kode_item', 'TB-TMB-002')->update(['tipe' => 'produk_tambahan']);
    }

    public function down(): void
    {
        DB::table('items')->where('tipe', 'produk_jual')->update(['tipe' => 'produk_jadi']);
        DB::table('items')->whereIn('tipe', ['tambahan_gratis', 'produk_tambahan'])->update(['tipe' => 'lainnya']);

        DB::statement("ALTER TABLE items MODIFY tipe ENUM(
            'bahan_baku','produk_jadi','kemasan','lainnya'
        ) NOT NULL DEFAULT 'bahan_baku'");
    }
};
