<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Setoran;
use App\Services\SetoranKasirService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Tahap 5 D'mentai — Setoran Kasir (rekonsiliasi harian cabang -> HO).
 * Filter cabang manual (CabangScope dormant, lihat CLAUDE.md 3.4/9.1.1).
 */
class SetoranKasirController extends Controller
{
    public function __construct(private SetoranKasirService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('setoran_kasir.view'), 403);

        $user = auth()->user();
        $query = Setoran::with(['cabang', 'disubmitOleh', 'disetujuiOleh'])->orderByDesc('tanggal');

        if (! $user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
            $query->where('cabang_id', $cabangId);
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $setorans = $query->paginate(20)->withQueryString();
        $cabangOptions = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        return view('setoran-kasir.index', compact('setorans', 'cabangOptions'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('setoran_kasir.create'), 403);

        $user = auth()->user();
        $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        abort_unless($cabangId, 422, 'Anda belum terdaftar di cabang manapun.');

        $tanggal = now()->format('Y-m-d');
        $hitung = $this->service->hitungOtomatis((int) $cabangId, $tanggal);
        $sudahAda = Setoran::where('cabang_id', $cabangId)->where('tanggal', $tanggal)->first();

        return view('setoran-kasir.create', compact('hitung', 'tanggal', 'sudahAda'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('setoran_kasir.create'), 403);

        $request->validate([
            'tanggal' => 'required|date',
            'total_disetor' => 'required|numeric|min:0',
            'catatan_kasir' => 'nullable|string|max:500',
            'bukti_foto' => 'nullable|image|max:2048',
        ]);

        $user = auth()->user();
        $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        abort_unless($cabangId, 422, 'Anda belum terdaftar di cabang manapun.');

        $buktiPath = $request->hasFile('bukti_foto') ? $request->file('bukti_foto')->store('setoran-kasir', 'public') : null;

        try {
            $setoran = $this->service->submitSetoran([
                'cabang_id' => $cabangId,
                'tanggal' => $request->tanggal,
                'user_id' => $user->id,
                'total_disetor' => $request->total_disetor,
                'catatan_kasir' => $request->catatan_kasir,
                'bukti_foto' => $buktiPath,
            ]);
        } catch (\Exception $e) {
            if ($buktiPath) {
                Storage::disk('public')->delete($buktiPath);
            }
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('setoran-kasir.show', $setoran)->with('success', 'Setoran berhasil disubmit, menunggu approval HO.');
    }

    public function show(Setoran $setoranKasir)
    {
        abort_unless(auth()->user()->can('setoran_kasir.view'), 403);

        $user = auth()->user();
        if (! $user->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();
            abort_unless($setoranKasir->cabang_id == $cabangId, 403, 'Setoran ini bukan milik cabang Anda.');
        }

        $setoranKasir->load(['cabang', 'disubmitOleh', 'disetujuiOleh', 'details', 'approvals.user']);

        return view('setoran-kasir.show', ['setoran' => $setoranKasir]);
    }

    public function approve(Request $request, Setoran $setoranKasir)
    {
        abort_unless(auth()->user()->can('setoran_kasir.approve'), 403);

        try {
            $this->service->approveSetoran($setoranKasir, auth()->id(), $request->input('catatan_ho'));
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('setoran-kasir.show', $setoranKasir)->with('success', 'Setoran disetujui — saldo Kas HO bertambah.');
    }

    public function reject(Request $request, Setoran $setoranKasir)
    {
        abort_unless(auth()->user()->can('setoran_kasir.reject'), 403);

        $request->validate(['alasan' => 'required|string|min:5|max:500']);

        try {
            $this->service->rejectSetoran($setoranKasir, auth()->id(), $request->alasan);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('setoran-kasir.show', $setoranKasir)->with('success', 'Setoran ditolak. Kasir bisa revise & submit ulang.');
    }
}
