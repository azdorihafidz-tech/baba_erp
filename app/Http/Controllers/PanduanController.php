<?php

namespace App\Http\Controllers;

use App\Models\Panduan;
use Illuminate\Http\Request;

class PanduanController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = Panduan::active()->orderBy('modul')->orderBy('urutan');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $semua    = $query->get();
        $byModul  = $semua->groupBy('modul');

        return view('panduan.index', compact('byModul', 'search'));
    }

    public function show(string $slug)
    {
        $panduan = Panduan::where('slug', $slug)->where('aktif', true)->firstOrFail();

        return view('panduan.show', compact('panduan'));
    }
}
