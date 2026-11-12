<?php

namespace App\Http\Controllers;

use App\Enums\TipeTransaksiKeuangan;
use App\Exports\LaporanArusKasExport;
use App\Exports\LaporanLabaRugiExport;
use App\Models\Cabang;
use App\Models\Scopes\CabangScope;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LaporanKeuanganController extends Controller
{
    public function labaRugi(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.keuangan.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        // Dari/sampai dengan session persistence
        if ($request->filled('dari') || $request->filled('sampai')) {
            $dari   = $request->filled('dari')   ? Carbon::parse($request->dari)->startOfDay()   : Carbon::now()->startOfMonth();
            $sampai = $request->filled('sampai') ? Carbon::parse($request->sampai)->endOfDay()   : Carbon::now()->endOfDay();
            session(['labarugi_dari' => $dari->toDateString(), 'labarugi_sampai' => $sampai->toDateString()]);
        } else {
            $dari   = Carbon::parse(session('labarugi_dari',   Carbon::now()->startOfMonth()->toDateString()))->startOfDay();
            $sampai = Carbon::parse(session('labarugi_sampai', Carbon::now()->toDateString()))->endOfDay();
        }

        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        // Pemasukan grouped by kategori
        // Catatan: withoutGlobalScope(CabangScope::class) — BUKAN withoutGlobalScopes()
        // tanpa argumen — supaya SoftDeletingScope tetap aktif (mencegah transaksi
        // dari order yang sudah dibatalkan/dihapus ikut ke-SUM sebagai omzet).
        $pemasukanQuery = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->where('tipe', TipeTransaksiKeuangan::Pemasukan)
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()]);
        \App\Support\KeuanganBaba::saring($pemasukanQuery, 'cabang_id', $cabangId); // Tahap 4: tanpa toko mitra

        $pemasukan = $pemasukanQuery->selectRaw('kategori, SUM(jumlah) as total')
            ->groupBy('kategori')
            ->get();
        $totalPemasukan = $pemasukan->sum('total');

        // Pengeluaran grouped by kategori
        $pengeluaranQuery = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->where('tipe', TipeTransaksiKeuangan::Pengeluaran)
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()]);
        \App\Support\KeuanganBaba::saring($pengeluaranQuery, 'cabang_id', $cabangId);

        $pengeluaran = $pengeluaranQuery->selectRaw('kategori, SUM(jumlah) as total')
            ->groupBy('kategori')
            ->get();
        $totalPengeluaran = $pengeluaran->sum('total');

        $labaRugi = $totalPemasukan - $totalPengeluaran;

        // Grafik bulanan 6 bulan terakhir (selalu trailing 6 bulan)
        $grafikLabels = [];
        $grafikPemasukan = [];
        $grafikPengeluaran = [];
        for ($i = 5; $i >= 0; $i--) {
            $bln = Carbon::now()->subMonths($i)->format('Y-m');
            $grafikLabels[] = Carbon::now()->subMonths($i)->translatedFormat('M Y');
            $qMasuk = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
                ->where('tipe', TipeTransaksiKeuangan::Pemasukan)
                ->where('tanggal_transaksi', 'like', $bln . '%');
            \App\Support\KeuanganBaba::saring($qMasuk, 'cabang_id', $cabangId);
            $grafikPemasukan[] = (float) $qMasuk->sum('jumlah');

            $qKeluar = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
                ->where('tipe', TipeTransaksiKeuangan::Pengeluaran)
                ->where('tanggal_transaksi', 'like', $bln . '%');
            \App\Support\KeuanganBaba::saring($qKeluar, 'cabang_id', $cabangId);
            $grafikPengeluaran[] = (float) $qKeluar->sum('jumlah');
        }

        if ($request->export === 'excel') {
            $periodeLabel = $dari->format('d/m/Y') . ' — ' . $sampai->format('d/m/Y');
            $filename = 'laporan-laba-rugi_' . $dari->toDateString() . '_sampai_' . $sampai->toDateString() . '.xlsx';

            return Excel::download(
                new LaporanLabaRugiExport($pemasukan, $pengeluaran, $totalPemasukan, $totalPengeluaran, $labaRugi, $periodeLabel),
                $filename
            );
        }

        return view('laporan.keuangan.laba-rugi', compact(
            'pemasukan', 'pengeluaran', 'totalPemasukan', 'totalPengeluaran', 'labaRugi',
            'cabangs', 'cabangId', 'dari', 'sampai',
            'grafikLabels', 'grafikPemasukan', 'grafikPengeluaran'
        ));
    }

    public function arusKas(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.keuangan.view'), 403);

        $user = auth()->user();
        $cabangs = Cabang::aktif()->get();

        $dari = $request->dari ? Carbon::parse($request->dari) : Carbon::now()->startOfMonth();
        $sampai = $request->sampai ? Carbon::parse($request->sampai) : Carbon::now();
        $cabangId = $request->cabang_id;
        if (!$cabangId && !$user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        }

        $query = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->with(['cabang', 'kategoriDinamis'])
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()]);
        \App\Support\KeuanganBaba::saring($query, 'cabang_id', $cabangId);

        $totalMasuk = (clone $query)->where('tipe', TipeTransaksiKeuangan::Pemasukan)->sum('jumlah');
        $totalKeluar = (clone $query)->where('tipe', TipeTransaksiKeuangan::Pengeluaran)->sum('jumlah');
        $saldo = $totalMasuk - $totalKeluar;

        if ($request->export === 'excel') {
            $transaksis = $query->orderBy('tanggal_transaksi')->get();
            $filename = 'laporan-arus-kas_' . $dari->toDateString() . '_sampai_' . $sampai->toDateString() . '.xlsx';

            return Excel::download(new LaporanArusKasExport($transaksis), $filename);
        }

        $transaksis = $query->orderBy('tanggal_transaksi')->paginate(30)->withQueryString();

        return view('laporan.keuangan.arus-kas', compact(
            'transaksis', 'cabangs', 'cabangId', 'dari', 'sampai',
            'totalMasuk', 'totalKeluar', 'saldo'
        ));
    }

    public function harian(Request $request)
    {
        return $this->arusKas($request);
    }

    public function pengeluaran(Request $request)
    {
        return $this->labaRugi($request);
    }

}
