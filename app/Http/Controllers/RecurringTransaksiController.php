<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\RecurringTransaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecurringTransaksiController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('recurring.view'), 403);

        $user     = auth()->user();
        $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();

        $query = RecurringTransaksi::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->with(['cabang:id,nama_cabang', 'kas:id,nama_kas', 'kategori:id,nama'])
            ->when(!$user->canAccessAllBranches(), fn($q) => $q->where('cabang_id', $cabangId))
            ->orderByDesc('is_active')
            ->orderBy('tanggal_jatuh_tempo');

        if ($request->filled('tipe')) $query->where('tipe', $request->tipe);

        $recurrings = $query->paginate(20)->withQueryString();

        return view('recurring.index', compact('recurrings'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('recurring.create'), 403);

        $user     = auth()->user();
        $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();

        $kasList = Kas::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when(!$user->canAccessAllBranches(), fn($q) => $q->where('cabang_id', $cabangId))
            ->with('cabang:id,nama_cabang')
            ->get(['id', 'cabang_id', 'nama_kas']);

        $kategoris = KategoriTransaksi::aktif()
            ->orderBy('parent_id')
            ->orderBy('urutan')
            ->get(['id', 'nama', 'tipe', 'parent_id']);

        $cabangs = $user->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get(['id', 'nama_cabang'])
            : collect();

        $kasOptions = $kasList;
        return view('recurring.create', compact('kasOptions', 'kategoris', 'cabangs', 'cabangId'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('recurring.create'), 403);

        $validated = $request->validate([
            'nama_template'       => 'required|string|max:150',
            'tipe'                => 'required|in:pemasukan,pengeluaran',
            'kategori_id'         => 'nullable|exists:kategori_transaksis,id',
            'cabang_id'           => 'nullable|exists:cabangs,id',
            'kas_id'              => 'nullable|exists:kas,id',
            'jumlah'              => 'required|numeric|min:0',
            'keterangan'          => 'required|string|max:255',
            'frekuensi'           => 'required|in:harian,mingguan,bulanan,tahunan',
            'tanggal_jatuh_tempo' => 'required|integer|min:1|max:31',
            'tanggal_mulai'       => 'required|date',
            'tanggal_akhir'       => 'nullable|date|after_or_equal:tanggal_mulai',
            'is_active'           => 'boolean',
            'auto_approve'        => 'boolean',
            'catatan'             => 'nullable|string',
        ]);

        $user     = auth()->user();
        $cabangId = $validated['cabang_id']
            ?? session('active_cabang_id')
            ?? $user->defaultCabangId();

        $jumlah = (float) str_replace('.', '', $validated['jumlah']);

        RecurringTransaksi::create(array_merge($validated, [
            'cabang_id'  => $cabangId,
            'jumlah'     => $jumlah,
            'is_active'  => $request->boolean('is_active', true),
            'auto_approve' => $request->boolean('auto_approve', false),
            'created_by' => auth()->id(),
        ]));

        return redirect()->route('recurring.index')->with('success', 'Template recurring berhasil ditambahkan.');
    }

    public function edit(RecurringTransaksi $recurring)
    {
        abort_unless(auth()->user()->can('recurring.edit'), 403);

        $user     = auth()->user();
        $cabangId = session('active_cabang_id') ?? $user->defaultCabangId();

        $kasList = Kas::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when(!$user->canAccessAllBranches(), fn($q) => $q->where('cabang_id', $cabangId))
            ->with('cabang:id,nama_cabang')
            ->get(['id', 'cabang_id', 'nama_kas']);

        $kategoris = KategoriTransaksi::aktif()
            ->orderBy('parent_id')
            ->orderBy('urutan')
            ->get(['id', 'nama', 'tipe', 'parent_id']);

        $cabangs = $user->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get(['id', 'nama_cabang'])
            : collect();

        $kasOptions = $kasList;
        return view('recurring.edit', compact('recurring', 'kasOptions', 'kategoris', 'cabangs'));
    }

    public function update(Request $request, RecurringTransaksi $recurring)
    {
        abort_unless(auth()->user()->can('recurring.edit'), 403);

        $validated = $request->validate([
            'nama_template'       => 'required|string|max:150',
            'tipe'                => 'required|in:pemasukan,pengeluaran',
            'kategori_id'         => 'nullable|exists:kategori_transaksis,id',
            'kas_id'              => 'nullable|exists:kas,id',
            'jumlah'              => 'required|numeric|min:0',
            'keterangan'          => 'required|string|max:255',
            'frekuensi'           => 'required|in:harian,mingguan,bulanan,tahunan',
            'tanggal_jatuh_tempo' => 'required|integer|min:1|max:31',
            'tanggal_mulai'       => 'required|date',
            'tanggal_akhir'       => 'nullable|date|after_or_equal:tanggal_mulai',
            'catatan'             => 'nullable|string',
        ]);

        $jumlah = (float) str_replace('.', '', $validated['jumlah']);

        $recurring->update(array_merge($validated, [
            'jumlah'     => $jumlah,
            'is_active'  => $request->boolean('is_active', true),
            'auto_approve' => $request->boolean('auto_approve', false),
        ]));

        return redirect()->route('recurring.index')->with('success', 'Template recurring berhasil diperbarui.');
    }

    public function destroy(RecurringTransaksi $recurring)
    {
        abort_unless(auth()->user()->can('recurring.delete'), 403);
        $nama = $recurring->nama_template;
        $recurring->delete();
        return back()->with('success', "Template \"{$nama}\" berhasil dihapus.");
    }

    public function toggleActive(RecurringTransaksi $recurring)
    {
        abort_unless(auth()->user()->can('recurring.edit'), 403);
        $recurring->update(['is_active' => !$recurring->is_active]);
        $status = $recurring->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Template \"{$recurring->nama_template}\" berhasil {$status}.");
    }

    /**
     * Manual trigger: generate transaksi hari ini untuk template ini.
     */
    public function generate(RecurringTransaksi $recurring)
    {
        abort_unless(auth()->user()->can('recurring.edit'), 403);

        try {
            DB::transaction(function () use ($recurring) {
                $recurring->generateTransaksi(Carbon::today());
                $recurring->update(['tanggal_terakhir_generate' => Carbon::today()]);
            });
            return back()->with('success', "Transaksi dari template \"{$recurring->nama_template}\" berhasil dibuat.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal generate: ' . $e->getMessage());
        }
    }
}
