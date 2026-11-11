<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\JenisOlahanRequest;
use App\Models\JenisOlahan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JenisOlahanController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('jenis-olahan.view'), 403);

        $query = JenisOlahan::orderBy('nama');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $jenisOlahans = $query->paginate(20)->withQueryString();

        return view('master.jenis-olahan.index', compact('jenisOlahans'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('jenis-olahan.manage'), 403);

        return view('master.jenis-olahan.create');
    }

    public function store(JenisOlahanRequest $request)
    {
        abort_unless(auth()->user()->can('jenis-olahan.manage'), 403);

        $data         = $request->validated();
        $data['slug'] = Str::slug($data['nama'], '_');

        // Cek duplikat slug (beda nama tapi hasilkan slug sama)
        if (JenisOlahan::where('slug', $data['slug'])->exists()) {
            return back()->withInput()->withErrors(['nama' => 'Nama ini menghasilkan slug yang sudah ada.']);
        }

        JenisOlahan::create($data);

        return redirect()->route('master.jenis-olahan.index')
            ->with('success', "Jenis Menu \"{$data['nama']}\" berhasil ditambahkan.");
    }

    public function edit(JenisOlahan $jenisOlahan)
    {
        abort_unless(auth()->user()->can('jenis-olahan.manage'), 403);

        return view('master.jenis-olahan.edit', compact('jenisOlahan'));
    }

    public function update(JenisOlahanRequest $request, JenisOlahan $jenisOlahan)
    {
        abort_unless(auth()->user()->can('jenis-olahan.manage'), 403);

        // Slug IMMUTABLE — hanya update nama & is_active
        $jenisOlahan->update([
            'nama'      => $request->validated()['nama'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('master.jenis-olahan.index')
            ->with('success', "Jenis Menu \"{$jenisOlahan->nama}\" berhasil diperbarui.");
    }

    public function destroy(JenisOlahan $jenisOlahan)
    {
        abort_unless(auth()->user()->can('jenis-olahan.manage'), 403);

        // Tidak hard delete — set is_active=false agar history transaksi tetap valid
        $jenisOlahan->update(['is_active' => false]);

        return redirect()->route('master.jenis-olahan.index')
            ->with('success', "Jenis Menu \"{$jenisOlahan->nama}\" dinonaktifkan (data transaksi lama tetap aman).");
    }
}
