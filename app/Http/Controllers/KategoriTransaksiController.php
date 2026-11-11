<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\KategoriTransaksi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriTransaksiController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('kategori.view'), 403);

        $kategoris = KategoriTransaksi::with(['children', 'chartOfAccount'])
            ->whereNull('parent_id')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        return view('kategori_transaksi.index', compact('kategoris'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('kategori.create'), 403);

        $parents = KategoriTransaksi::aktif()
            ->whereNull('parent_id')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
        $coaList = ChartOfAccount::where('is_leaf', true)->orderBy('kode')->get();

        return view('kategori_transaksi.create', compact('parents', 'coaList'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('kategori.create'), 403);

        $data = $request->validate([
            'kode'          => ['required', 'string', 'max:30', Rule::unique('kategori_transaksis', 'kode')],
            'nama'          => 'required|string|max:100',
            'tipe'          => 'required|in:pemasukan,pengeluaran,keduanya',
            'parent_id'     => 'nullable|exists:kategori_transaksis,id',
            'urutan'        => 'nullable|integer|min:0|max:9999',
            'is_active'     => 'boolean',
            'kode_akun_coa' => 'nullable|exists:chart_of_accounts,kode',
            'tipe_biaya'    => 'nullable|in:tetap,variabel',
        ]);

        $data['kode']      = strtoupper(trim($data['kode']));
        $data['is_system'] = false;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['urutan']    = $data['urutan'] ?? 50;

        KategoriTransaksi::create($data);

        return redirect()->route('kategori-transaksi.index')
            ->with('success', 'Kategori "' . $data['nama'] . '" berhasil ditambahkan.');
    }

    public function edit(KategoriTransaksi $kategoriTransaksi)
    {
        abort_unless(auth()->user()->can('kategori.edit'), 403);

        $parents = KategoriTransaksi::aktif()
            ->whereNull('parent_id')
            ->where('id', '!=', $kategoriTransaksi->id)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
        $coaList = ChartOfAccount::where('is_leaf', true)->orderBy('kode')->get();

        return view('kategori_transaksi.edit', compact('kategoriTransaksi', 'parents', 'coaList'));
    }

    public function update(Request $request, KategoriTransaksi $kategoriTransaksi)
    {
        abort_unless(auth()->user()->can('kategori.edit'), 403);

        $data = $request->validate([
            'kode'          => ['required', 'string', 'max:30',
                                Rule::unique('kategori_transaksis', 'kode')->ignore($kategoriTransaksi->id)],
            'nama'          => 'required|string|max:100',
            'tipe'          => 'required|in:pemasukan,pengeluaran,keduanya',
            'parent_id'     => ['nullable', 'exists:kategori_transaksis,id',
                                Rule::notIn([$kategoriTransaksi->id])],
            'urutan'        => 'nullable|integer|min:0|max:9999',
            'is_active'     => 'boolean',
            'kode_akun_coa' => 'nullable|exists:chart_of_accounts,kode',
            'tipe_biaya'    => 'nullable|in:tetap,variabel',
        ]);

        $data['kode']      = strtoupper(trim($data['kode']));
        $data['is_active'] = $request->boolean('is_active', true);
        $data['urutan']    = $data['urutan'] ?? 50;

        $kategoriTransaksi->update($data);

        return redirect()->route('kategori-transaksi.index')
            ->with('success', 'Kategori "' . $kategoriTransaksi->nama . '" berhasil diperbarui.');
    }

    public function destroy(KategoriTransaksi $kategoriTransaksi)
    {
        abort_unless(auth()->user()->can('kategori.delete'), 403);

        if ($kategoriTransaksi->is_system) {
            return back()->with('error', 'Kategori sistem tidak bisa dihapus.');
        }

        if ($kategoriTransaksi->transaksis()->exists()) {
            return back()->with('error', 'Kategori ini sudah dipakai oleh ' . $kategoriTransaksi->transaksis()->count() . ' transaksi. Nonaktifkan saja.');
        }

        if ($kategoriTransaksi->children()->exists()) {
            return back()->with('error', 'Kategori ini memiliki sub-kategori. Hapus sub-kategori dulu.');
        }

        $nama = $kategoriTransaksi->nama;
        $kategoriTransaksi->delete();

        return redirect()->route('kategori-transaksi.index')
            ->with('success', "Kategori \"{$nama}\" berhasil dihapus.");
    }

    /** AJAX: ambil daftar kategori berdasarkan tipe (untuk filter di form transaksi) */
    public function forTipe(Request $request)
    {
        $tipe = $request->input('tipe');
        $query = KategoriTransaksi::aktif()->orderBy('urutan')->orderBy('nama');

        if ($tipe) {
            $query->where(function ($q) use ($tipe) {
                $q->where('tipe', $tipe)->orWhere('tipe', 'keduanya');
            });
        }

        $kategoris = $query->get(['id', 'kode', 'nama', 'tipe', 'parent_id']);

        return response()->json($kategoris);
    }
}
