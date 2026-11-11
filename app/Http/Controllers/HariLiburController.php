<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\HariLibur;
use Illuminate\Http\Request;

class HariLiburController extends Controller
{
    public function index(Request $request)
    {
        $tahun = $request->input('tahun', now()->year);
        $tipe  = $request->input('tipe', '');

        $query = HariLibur::with('cabang')
            ->whereYear('tanggal', $tahun)
            ->when($tipe, fn($q) => $q->where('tipe', $tipe))
            ->orderBy('tanggal');

        $liburs  = $query->paginate(30)->withQueryString();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('hari-libur.index', compact('liburs', 'cabangs', 'tahun', 'tipe'));
    }

    public function create()
    {
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();
        return view('hari-libur.create', compact('cabangs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tanggal'   => ['required', 'date'],
            'nama'      => ['required', 'string', 'max:100'],
            'tipe'      => ['required', 'in:nasional,cabang'],
            'cabang_id' => ['nullable', 'required_if:tipe,cabang', 'exists:cabangs,id'],
        ]);

        if ($data['tipe'] === 'nasional') {
            $data['cabang_id'] = null;
        }

        HariLibur::create($data);

        return redirect()->route('hari-libur.index')
            ->with('success', 'Hari libur berhasil ditambahkan.');
    }

    public function edit(HariLibur $hariLibur)
    {
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();
        return view('hari-libur.edit', compact('hariLibur', 'cabangs'));
    }

    public function update(Request $request, HariLibur $hariLibur)
    {
        $data = $request->validate([
            'tanggal'   => ['required', 'date'],
            'nama'      => ['required', 'string', 'max:100'],
            'tipe'      => ['required', 'in:nasional,cabang'],
            'cabang_id' => ['nullable', 'required_if:tipe,cabang', 'exists:cabangs,id'],
        ]);

        if ($data['tipe'] === 'nasional') {
            $data['cabang_id'] = null;
        }

        $hariLibur->update($data);

        return redirect()->route('hari-libur.index')
            ->with('success', 'Hari libur berhasil diperbarui.');
    }

    public function destroy(HariLibur $hariLibur)
    {
        $nama = $hariLibur->nama;
        $hariLibur->delete();

        return back()->with('success', "Hari libur \"{$nama}\" berhasil dihapus.");
    }
}
