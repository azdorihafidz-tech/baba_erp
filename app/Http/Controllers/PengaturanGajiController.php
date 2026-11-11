<?php

namespace App\Http\Controllers;

use App\Models\PengaturanGaji;
use Illuminate\Http\Request;

class PengaturanGajiController extends Controller
{
    public function edit()
    {
        $setting = PengaturanGaji::getSetting();
        return view('pengaturan.penggajian', compact('setting'));
    }

    public function update(Request $request)
    {
        // Strip titik ribuan dari input rupiah (safety: JS harusnya sudah strip, tapi ini belt-and-suspenders)
        foreach (['tarif_lembur_per_jam', 'potongan_alpa_per_hari', 'potongan_telat_per_menit'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => (int) str_replace('.', '', $request->input($field, '0'))]);
            }
        }

        $data = $request->validate([
            'tarif_lembur_per_jam'     => 'required|numeric|min:0',
            'potongan_alpa_per_hari'   => 'required|numeric|min:0',
            'potongan_telat_per_menit' => 'required|numeric|min:0',
            'hari_kerja_per_minggu'    => 'required|integer|min:1|max:7',
        ]);

        $setting = PengaturanGaji::getSetting();
        $setting->update($data);

        return back()->with('success', 'Pengaturan penggajian berhasil disimpan.');
    }
}
