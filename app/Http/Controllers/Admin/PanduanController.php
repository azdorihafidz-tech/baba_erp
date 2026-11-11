<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PanduanRequest;
use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PanduanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $modul  = $request->input('modul');

        $query = Panduan::orderBy('modul')->orderBy('urutan');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }
        if ($modul) {
            $query->where('modul', $modul);
        }

        $panduan = $query->paginate(20)->withQueryString();
        $moduls  = Panduan::select('modul')->distinct()->orderBy('modul')->pluck('modul');

        return view('admin.panduan.index', compact('panduan', 'moduls', 'search', 'modul'));
    }

    public function create()
    {
        $panduan = new Panduan();
        $moduls  = $this->getAllModuls();
        return view('admin.panduan.create', compact('panduan', 'moduls'));
    }

    public function store(PanduanRequest $request)
    {
        Panduan::create($request->validated());

        return redirect()->route('admin.panduan.index')
            ->with('success', 'Panduan berhasil ditambahkan.');
    }

    public function edit(Panduan $panduan)
    {
        $moduls = $this->getAllModuls();
        return view('admin.panduan.edit', compact('panduan', 'moduls'));
    }

    private function getAllModuls(): \Illuminate\Support\Collection
    {
        $fromPanduan = Panduan::select('modul')->distinct()->pluck('modul');
        $fromTooltip = Tooltip::select('modul')->distinct()->pluck('modul');
        return $fromPanduan->merge($fromTooltip)->unique()->sort()->values();
    }

    public function update(PanduanRequest $request, Panduan $panduan)
    {
        $panduan->update($request->validated());

        return redirect()->route('admin.panduan.index')
            ->with('success', 'Panduan berhasil diperbarui.');
    }

    public function destroy(Panduan $panduan)
    {
        Cache::forget("panduan:{$panduan->slug}");
        $panduan->delete();

        return redirect()->route('admin.panduan.index')
            ->with('success', 'Panduan berhasil dihapus.');
    }

    public function toggleAktif(Panduan $panduan)
    {
        $panduan->update(['aktif' => ! $panduan->aktif]);
        Cache::forget("panduan:{$panduan->slug}");

        $status = $panduan->aktif ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Panduan berhasil {$status}.");
    }
}
