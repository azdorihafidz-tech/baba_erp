<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AbsensiSayaController extends Controller
{
    public function index(Request $request)
    {
        $user     = auth()->user();
        $karyawan = $user->karyawan;

        if (!$karyawan) {
            return view('absensi-saya.index', [
                'karyawan'  => null,
                'absensis'  => collect(),
                'stats'     => [],
                'dari'      => null,
                'sampai'    => null,
            ]);
        }

        $dari   = $request->filled('dari')
            ? Carbon::parse($request->dari)->startOfDay()
            : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai')
            ? Carbon::parse($request->sampai)->endOfDay()
            : Carbon::now()->endOfMonth();

        $absensis = Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('karyawan_id', $karyawan->id)
            ->whereBetween('tanggal', [$dari->format('Y-m-d'), $sampai->format('Y-m-d')])
            ->orderBy('tanggal', 'desc')
            ->get();

        $stats = [
            'hadir'      => $absensis->where('status', 'hadir')->count(),
            'izin'       => $absensis->where('status', 'izin')->count(),
            'sakit'      => $absensis->where('status', 'sakit')->count(),
            'alpha'      => $absensis->where('status', 'alpha')->count(),
            'jam_lembur' => round((float) $absensis->sum('jam_lembur'), 1),
        ];

        return view('absensi-saya.index', compact('karyawan', 'absensis', 'stats', 'dari', 'sampai'));
    }
}
