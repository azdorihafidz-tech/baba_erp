<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: skip jika sudah ada batch
        if (DB::table('stock_batches')->count() > 0) {
            return;
        }

        $stocks = DB::table('stocks')
            ->where('qty', '>', 0)
            ->whereNull('deleted_at')
            ->get();

        $now = now();
        // Tanggal batch awal = 1 tahun lalu agar selalu di-consume FIFO sebelum batch baru
        $tanggalAwal = $now->copy()->subYear()->toDateString();

        foreach ($stocks as $stock) {
            $item = DB::table('items')
                ->where('id', $stock->item_id)
                ->whereNull('deleted_at')
                ->first();

            if (!$item) continue;

            $hargaBeli = (float) ($item->harga_beli_terakhir ?? 0);

            // Buat batch bahkan jika harga 0 supaya qty_sisa match dengan stock
            DB::table('stock_batches')->insert([
                'item_id'             => $stock->item_id,
                'lokasi_id'           => $stock->lokasi_id,
                'referensi_type'      => 'initial',
                'referensi_id'        => null,
                'qty_awal'            => $stock->qty,
                'qty_sisa'            => $stock->qty,
                'harga_beli_per_unit' => $hargaBeli,
                'tanggal_masuk'       => $tanggalAwal,
                'catatan'             => 'Initial batch dari migrasi FIFO',
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('stock_batches')->where('referensi_type', 'initial')->delete();
    }
};
