<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('coa.view'), 403);

        $query = ChartOfAccount::orderBy('kode');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                  ->orWhere('nama', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        $akunList = $query->get();
        $tipeList = [
            'aset' => 'Aset', 'kewajiban' => 'Kewajiban', 'modal' => 'Modal',
            'pendapatan' => 'Pendapatan', 'hpp' => 'HPP',
            'beban_operasional' => 'Beban Operasional',
            'pendapatan_lain' => 'Pendapatan Lain', 'beban_lain' => 'Beban Lain',
        ];

        return view('coa.index', compact('akunList', 'tipeList'));
    }
}
