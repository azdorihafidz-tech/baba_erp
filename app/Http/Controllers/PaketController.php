<?php

namespace App\Http\Controllers;

use App\Support\Paket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pengaturan → Paket & Langganan (khusus Tim IT, route role:teknisi).
 * porting Dimsum 4.53 (_rancangan/RANCANGAN-KAMUS-TIMIT-PAKET.md) bagian 3–6.
 */
class PaketController extends Controller
{
    public function index()
    {
        Paket::lupakan();

        return view('paket.index', ['data' => Paket::data(), 'status' => Paket::statusLangganan()]);
    }

    public function update(Request $request)
    {
        $v = $request->validate([
            'modul'         => ['nullable', 'array'],
            'modul.*'       => [Rule::in(array_keys(Paket::MODUL))],
            'aktif_sampai'  => ['nullable', 'date'],
            'masa_tenggang' => ['required', 'integer', 'min:0', 'max:60'],
        ], ['masa_tenggang.max' => 'Masa tenggang paling lama 60 hari.']);

        $hasil = Paket::simpan($v['modul'] ?? [], $v['aktif_sampai'] ?? null, (int) $v['masa_tenggang'], auth()->id());
        activity('TimIT')->causedBy(auth()->user())->withProperties(['old' => $hasil['lama'], 'attributes' => $hasil['baru']])
            ->log('Ubah paket & langganan: ' . Paket::namaPaket($hasil['baru']['modul']));

        $tambahan = array_diff($hasil['baru']['modul'], $v['modul'] ?? []);
        $pesan = 'Paket disimpan (' . Paket::namaPaket() . ').';
        if ($tambahan) {
            $pesan .= ' Ikut dinyalakan karena dibutuhkan: ' . implode(', ', array_map(fn ($m) => Paket::MODUL[$m][0], $tambahan)) . '.';
        }

        return back()->with('success', $pesan);
    }
}
