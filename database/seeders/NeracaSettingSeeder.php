<?php

namespace Database\Seeders;

use App\Models\NeracaSetting;
use Illuminate\Database\Seeder;

/**
 * Isi nilai default Modal Owner untuk Neraca — modal awal usaha (Rp
 * 198.329.749) tercatat historis sebagai Transfer/Perpindahan Dana
 * (TransaksiKeuangan id 231/232, "MODAL AWAL PERIODE PERTAMA"), BUKAN
 * kategori Modal tersendiri — jadi tidak bisa di-derive otomatis dari
 * transaksi. Idempotent: hanya isi kalau baris masih 0 (belum pernah
 * di-set manual oleh Owner via UI).
 */
class NeracaSettingSeeder extends Seeder
{
    public function run(): void
    {
        $setting = NeracaSetting::getSetting();

        if ((float) $setting->modal_owner == 0.0) {
            $setting->update([
                'modal_owner' => 198329749,
                'catatan' => 'Default dari histori TransaksiKeuangan id 231/232 (MODAL AWAL PERIODE PERTAMA). Update manual kalau ada modal tambahan masuk.',
            ]);
            $this->command->info('  NeracaSettingSeeder: modal_owner di-set ke Rp198.329.749 (default histori).');
        } else {
            $this->command->info('  NeracaSettingSeeder: modal_owner sudah pernah di-set (' . $setting->modal_owner . '), tidak ditimpa.');
        }
    }
}
