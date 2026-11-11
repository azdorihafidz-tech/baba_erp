<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TooltipRequest;
use App\Models\Panduan;
use App\Models\Tooltip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TooltipController extends Controller
{
    public function index(Request $request)
    {
        $query = Tooltip::query();

        if ($request->filled('modul')) {
            $query->where('modul', $request->modul);
        }
        if ($request->filled('search')) {
            $query->where('key', 'like', '%' . $request->search . '%');
        }

        $tooltips = $query->orderBy('modul')->orderBy('urutan')->paginate(20)->withQueryString();
        $moduls   = Tooltip::select('modul')->distinct()->orderBy('modul')->pluck('modul');

        return view('admin.tooltips.index', compact('tooltips', 'moduls'));
    }

    public function create()
    {
        $moduls = $this->getAllModuls();
        return view('admin.tooltips.create', compact('moduls'));
    }

    public function store(TooltipRequest $request)
    {
        Tooltip::create($request->validated());

        return redirect()->route('admin.tooltips.index')
            ->with('success', 'Tooltip berhasil ditambahkan.');
    }

    public function edit(Tooltip $tooltip)
    {
        $moduls = $this->getAllModuls();
        return view('admin.tooltips.edit', compact('tooltip', 'moduls'));
    }

    private function getAllModuls(): \Illuminate\Support\Collection
    {
        $fromPanduan = Panduan::select('modul')->distinct()->pluck('modul');
        $fromTooltip = Tooltip::select('modul')->distinct()->pluck('modul');
        return $fromPanduan->merge($fromTooltip)->unique()->sort()->values();
    }

    public function update(TooltipRequest $request, Tooltip $tooltip)
    {
        $tooltip->update($request->validated());

        return redirect()->route('admin.tooltips.index')
            ->with('success', 'Tooltip berhasil diperbarui.');
    }

    public function destroy(Tooltip $tooltip)
    {
        Cache::forget("tooltip:{$tooltip->key}");
        $tooltip->delete();

        return redirect()->route('admin.tooltips.index')
            ->with('success', 'Tooltip berhasil dihapus.');
    }

    public function toggleAktif(Tooltip $tooltip)
    {
        $tooltip->update(['aktif' => !$tooltip->aktif]);

        return redirect()->route('admin.tooltips.index')
            ->with('success', 'Status tooltip ' . ($tooltip->aktif ? 'diaktifkan' : 'dinonaktifkan') . '.');
    }
}
