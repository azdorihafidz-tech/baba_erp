<?php

namespace App\Http\Controllers;

use App\Enums\KategoriPengeluaran;
use App\Enums\KategoriTransaksi;
use App\Enums\TipeTransaksiKeuangan;
use App\Exports\TransaksiKeuanganExport;
use App\Models\Cabang;
use App\Models\Kas;
use App\Models\KategoriTransaksi as KategoriModel;
use App\Models\PurchaseOrder;
use App\Models\Scopes\CabangScope;
use App\Models\TransaksiKeuangan;
use App\Services\PoDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class KeuanganController extends Controller
{
    /**
     * Daftar transaksi keuangan dengan filter
     */
    /**
     * Query builder + filter yang aktif — DIREUSE oleh index() dan export()
     * supaya logic filter (tanggal/cabang/tipe/kategori/search) tidak pernah
     * ditulis 2x dan otomatis konsisten (export selalu ikut filter yang
     * sedang aktif di halaman).
     *
     * @return array{query: \Illuminate\Database\Eloquent\Builder, dari: Carbon, sampai: Carbon, filterCabangId: ?int, isSemua: bool}
     */
    private function buildFilteredQuery(Request $request): array
    {
        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id');

        // Tanggal dari/sampai — default bulan ini
        $dari   = $request->filled('dari')
            ? Carbon::parse($request->dari)->startOfDay()
            : Carbon::now()->startOfMonth();
        $sampai = $request->filled('sampai')
            ? Carbon::parse($request->sampai)->endOfDay()
            : Carbon::now()->endOfDay();

        // Simpan ke session agar persist saat pindah halaman
        if ($request->filled('dari') || $request->filled('sampai')) {
            session(['keuangan_dari' => $dari->toDateString(), 'keuangan_sampai' => $sampai->toDateString()]);
        } elseif (!$request->filled('dari')) {
            // Restore dari session jika tidak ada request param
            $dariSess   = session('keuangan_dari');
            $sampaiSess = session('keuangan_sampai');
            if ($dariSess)   $dari   = Carbon::parse($dariSess)->startOfDay();
            if ($sampaiSess) $sampai = Carbon::parse($sampaiSess)->endOfDay();
        }

        // Tentukan filter cabang
        $isSemua = false;
        if (!$user->canAccessAllBranches()) {
            $filterCabangId = $activeCabangId ?? $user->defaultCabangId();
        } elseif ($activeCabangId) {
            $filterCabangId = $activeCabangId;
        } elseif ($request->filled('cabang_id')) {
            $filterCabangId = $request->cabang_id;
        } else {
            $filterCabangId = null;
            $isSemua        = $user->canAccessAllBranches();
        }

        $query = TransaksiKeuangan::with(['cabang', 'kas', 'createdBy', 'kategoriDinamis'])
            ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id');

        if ($filterCabangId) {
            $query->where('cabang_id', $filterCabangId);
        }

        // Filter tipe & kategori
        if ($request->filled('tipe'))        $query->where('tipe', $request->tipe);
        if ($request->filled('kategori_id')) $query->where('kategori_id', $request->kategori_id);
        elseif ($request->filled('kategori')) $query->where('kategori', $request->kategori);

        // Search multi-field — TAMBAHAN, tidak mengganti filter tipe/kategori/tanggal di atas
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_transaksi', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('kas', fn ($k) => $k->where('nama_kas', 'like', "%{$search}%"))
                  ->orWhereHas('kategoriDinamis', fn ($k) => $k->where('nama', 'like', "%{$search}%"));
            });
        }

        return compact('query', 'dari', 'sampai', 'filterCabangId', 'isSemua');
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('keuangan.view'), 403);

        ['query' => $query, 'dari' => $dari, 'sampai' => $sampai, 'filterCabangId' => $filterCabangId, 'isSemua' => $isSemua]
            = $this->buildFilteredQuery($request);
        $user = auth()->user();

        // Total untuk periode yang difilter
        $totalPemasukan  = (clone $query)->where('tipe', TipeTransaksiKeuangan::Pemasukan)->sum('jumlah');
        $totalPengeluaran = (clone $query)->where('tipe', TipeTransaksiKeuangan::Pengeluaran)->sum('jumlah');
        $saldo           = $totalPemasukan - $totalPengeluaran;

        $transaksis = $query->paginate(20)->withQueryString();

        // Rincian Saldo per Kas — TAMBAHAN murni untuk section "posisi saldo
        // real-time", TIDAK mengubah/menyentuh $totalPemasukan/$totalPengeluaran/
        // $saldo di atas maupun $query transaksi. Saldo di sini SELALU real-time
        // (Kas.saldo_sekarang), tidak terikat filter tanggal $dari/$sampai —
        // beda sengaja dari kartu "Selisih (Saldo)" yang mengukur arus kas
        // periode terpilih (2 angka berbeda secara definisi, bukan bug).
        $kasBreakdown = Kas::aktif()
            ->when($filterCabangId, fn ($q) => $q->where('cabang_id', $filterCabangId))
            ->orderBy('nama_kas')
            ->get(['id', 'nama_kas', 'tipe_kas', 'saldo_sekarang', 'cabang_id']);
        $totalSemuaKas = (float) $kasBreakdown->sum('saldo_sekarang');

        // Ringkasan per cabang (hanya untuk mode semua cabang)
        $ringkasanCabang = collect();
        if ($isSemua) {
            // withoutGlobalScope(CabangScope::class) — BUKAN withoutGlobalScopes()
            // tanpa argumen — supaya SoftDeletingScope tetap aktif (Bug 1).
            $ringkasanCabang = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
                ->with('cabang')
                ->whereBetween('tanggal_transaksi', [$dari->toDateString(), $sampai->toDateString()])
                ->selectRaw('cabang_id,
                    SUM(CASE WHEN tipe = ? THEN jumlah ELSE 0 END) as total_masuk,
                    SUM(CASE WHEN tipe = ? THEN jumlah ELSE 0 END) as total_keluar',
                    [TipeTransaksiKeuangan::Pemasukan->value, TipeTransaksiKeuangan::Pengeluaran->value])
                ->groupBy('cabang_id')
                ->get()
                ->map(function ($row) {
                    $row->saldo = $row->total_masuk - $row->total_keluar;
                    return $row;
                });
        }

        // Cleanup Tool B2 — transaksi pengeluaran/pemasukan yang belum punya Kas
        // Sumber (kas_id NULL). Sengaja exclude SEMUA transaksi yang punya
        // referensi_type (order/purchase_order/stock_movement/kas) — itu bukan
        // celah data, tapi entry yang MEMANG didesain tanpa kas fisik (mis.
        // beban kerugian Adjustment Stok susut/rusak/hilang, lihat
        // StokService::adjustment()). Tidak dibatasi filter tanggal/pagination
        // di atas karena ini murni tool cleanup data historis, bukan laporan.
        $tanpaKasSumber = collect();
        $kasPerCabang   = collect();
        if ($user->can('transaksi.assign_kas.action')) {
            $tanpaKasSumberQuery = TransaksiKeuangan::with('cabang')
                ->whereNull('kas_id')
                ->whereNull('referensi_type');
            if ($filterCabangId) {
                $tanpaKasSumberQuery->where('cabang_id', $filterCabangId);
            }
            $tanpaKasSumber = $tanpaKasSumberQuery->orderByDesc('tanggal_transaksi')->get();
            $kasPerCabang   = Kas::aktif()->orderBy('nama_kas')->get()->groupBy('cabang_id');
        }

        $tipes     = TipeTransaksiKeuangan::cases();
        $kategoris = KategoriModel::aktif()->orderBy('urutan')->orderBy('nama')->get();
        $cabangs   = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        return view('keuangan.index', compact(
            'transaksis', 'tipes', 'kategoris', 'cabangs',
            'totalPemasukan', 'totalPengeluaran', 'saldo',
            'dari', 'sampai', 'isSemua', 'filterCabangId', 'ringkasanCabang',
            'tanpaKasSumber', 'kasPerCabang',
            'kasBreakdown', 'totalSemuaKas'
        ));
    }

    /**
     * Export Excel — reuse buildFilteredQuery() yang SAMA dengan index(),
     * jadi export SELALU ikut filter (tanggal/cabang/tipe/kategori/search)
     * yang sedang aktif, bukan seluruh data.
     */
    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('keuangan.view'), 403);

        ['query' => $query, 'dari' => $dari, 'sampai' => $sampai] = $this->buildFilteredQuery($request);

        $filename = 'transaksi-kas_' . $dari->format('Y-m-d') . '_sampai_' . $sampai->format('Y-m-d')
            . '_' . now()->format('His') . '.xlsx';

        return Excel::download(new TransaksiKeuanganExport($query), $filename);
    }

    /**
     * Form tambah transaksi manual
     */
    public function create()
    {
        abort_unless(auth()->user()->can('keuangan.create'), 403);

        $user = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $cabangId = $activeCabangId ?? $user->defaultCabangId();

        $kass = Kas::aktif()->where('cabang_id', $cabangId)->get();
        $tipes = TipeTransaksiKeuangan::cases();
        $kategoris = KategoriModel::aktif()->orderBy('urutan')->orderBy('nama')->get();
        $kategoriPengeluarans = KategoriPengeluaran::cases();

        return view('keuangan.create', compact('kass', 'tipes', 'kategoris', 'kategoriPengeluarans'));
    }

    /**
     * AJAX: daftar PO diterima yang belum dibayar (belum ada transaksi_keuangan
     * referensi) — dipakai dropdown "Pilih PO (opsional)" di form Kas Keluar.
     * Murni helper auto-fill, tidak menyentuh logic simpan transaksi.
     */
    public function poPendingList(Request $request, PoDashboardService $poDashboardService)
    {
        abort_unless(auth()->user()->can('kas.pilih_po.view'), 403);

        $user = auth()->user();
        $cabangId = $request->cabang_id
            ?? session('active_cabang_id')
            ?? $user->defaultCabangId();

        $kategoriPbb = KategoriModel::where('kode', 'PBB')->first();

        $list = $poDashboardService->getPoPendingBayar($cabangId ? (int) $cabangId : null);

        return response()->json([
            'data' => $list->map(fn ($po) => [
                'id'                          => $po->id,
                'nomor_po'                    => $po->nomor_po,
                'supplier'                    => $po->nama_supplier ?? '-',
                'total'                       => (float) $po->total_harga,
                'tanggal_terima'              => $po->tanggal_terima,
                'umur_hari'                   => (int) round($po->umur_hari),
                'kategori_id_default'         => $kategoriPbb?->id,
                'kategori_pengeluaran_default' => 'bahan_baku',
                'keterangan_default'          => 'PO #' . $po->nomor_po . ' - ' . ($po->nama_supplier ?? '-'),
            ])->values(),
        ]);
    }

    /**
     * Simpan transaksi baru
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('keuangan.create'), 403);

        $request->validate([
            'tanggal_transaksi' => 'required|date',
            'tipe'              => 'required|in:' . implode(',', array_column(TipeTransaksiKeuangan::cases(), 'value')),
            'kategori_id'       => 'required|exists:kategori_transaksis,id',
            'kategori_pengeluaran' => 'required_if:tipe,' . TipeTransaksiKeuangan::Pengeluaran->value
                . '|nullable|in:' . implode(',', array_column(KategoriPengeluaran::cases(), 'value')),
            'keterangan'        => 'required|string|max:255',
            'jumlah'            => 'required|numeric|min:1',
            // Wajib pilih Kas — sebelumnya nullable, menyebabkan transaksi tidak
            // pernah mengurangi/menambah saldo kas manapun padahal tetap ke-SUM
            // di Laporan Keuangan (Bug 3, audit produksi 2026-07-26).
            'kas_id'            => 'required|exists:kas,id',
            'catatan'           => 'nullable|string',
            'bukti'             => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'po_id'             => 'nullable|exists:purchase_orders,id',
        ], [
            'kas_id.required' => 'Kas wajib dipilih.',
        ]);

        // Cegah double bayar: PO yang sama tidak boleh dicatat pembayarannya 2x.
        // Helper tool "Pilih PO" cuma auto-isi field di atas — validasi ini murni
        // pengaman tambahan, tidak mengubah alur simpan transaksi manual sama sekali.
        if ($request->filled('po_id')) {
            $sudahDibayar = TransaksiKeuangan::where('referensi_type', 'purchase_order')
                ->where('referensi_id', $request->po_id)
                ->exists();
            if ($sudahDibayar) {
                return back()->withInput()->with('error', 'PO ini sudah tercatat dibayar sebelumnya — tidak bisa dicatat dua kali.');
            }

            // Defense-in-depth (Rule #40): nominal WAJIB sama persis dengan total
            // PO kalau po_id terisi — pasangan server-side dari lock readonly di
            // frontend (terapkanPo()), supaya bypass JS (dev tools/curl langsung)
            // tetap ditolak. Cuma berlaku saat po_id ADA; transaksi manual biasa
            // tanpa PO tidak tersentuh sama sekali.
            $po = PurchaseOrder::find($request->po_id);
            if (!$po) {
                return back()->withErrors(['po_id' => 'PO tidak ditemukan.'])->withInput();
            }
            if ((int) $request->jumlah !== (int) $po->total_harga) {
                return back()->withErrors([
                    'jumlah' => 'Nominal harus sama persis dengan total PO Rp '
                        . number_format($po->total_harga, 0, ',', '.') . '.',
                ])->withInput();
            }
        }

        $user = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $cabangId = $activeCabangId ?? $user->defaultCabangId();

        $kategoriObj = KategoriModel::findOrFail($request->kategori_id);
        $kategoriPengeluaran = $request->tipe === TipeTransaksiKeuangan::Pengeluaran->value
            ? $request->kategori_pengeluaran
            : null;

        // Upload bukti jika ada
        $buktiPath = null;
        if ($request->hasFile('bukti')) {
            $buktiPath = $request->file('bukti')->store('bukti_transaksi', 'public');
        }

        // Generate nomor transaksi
        $prefix = 'TRX-' . date('Ymd');
        $lastNomor = TransaksiKeuangan::withTrashed()
            ->where('nomor_transaksi', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('nomor_transaksi');
        $seq = 1;
        if ($lastNomor && preg_match('/(\d+)$/', $lastNomor, $m)) {
            $seq = ((int) $m[1]) + 1;
        }
        $nomorTransaksi = $prefix . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($request, $cabangId, $nomorTransaksi, $kategoriObj, $kategoriPengeluaran, $buktiPath) {
            TransaksiKeuangan::create([
                'cabang_id'         => $cabangId,
                'kas_id'            => $request->kas_id,
                'nomor_transaksi'   => $nomorTransaksi,
                'tanggal_transaksi' => $request->tanggal_transaksi,
                'tipe'              => $request->tipe,
                'kategori'          => $kategoriObj->toEnumValue(),
                'kategori_id'       => $kategoriObj->id,
                'kategori_pengeluaran' => $kategoriPengeluaran,
                'keterangan'        => $request->keterangan,
                'jumlah'            => $request->jumlah,
                'bukti_path'        => $buktiPath,
                'catatan'           => $request->catatan,
                'created_by'        => auth()->id(),
                // Link ke PO (reuse pola polymorphic referensi_type/referensi_id
                // yang sudah dipakai PenjualanService untuk order) — nullable,
                // NULL kalau transaksi ini bukan pembayaran PO.
                'referensi_type'    => $request->filled('po_id') ? 'purchase_order' : null,
                'referensi_id'      => $request->filled('po_id') ? $request->po_id : null,
            ]);

            if ($request->kas_id) {
                $kas = Kas::find($request->kas_id);
                if ($kas) {
                    if ($request->tipe === TipeTransaksiKeuangan::Pemasukan->value) {
                        $kas->increment('saldo_sekarang', $request->jumlah);
                    } else {
                        $kas->decrement('saldo_sekarang', $request->jumlah);
                    }
                }
            }
        });

        return redirect()->route('keuangan.index')->with('success', 'Transaksi berhasil disimpan.');
    }

    /**
     * Detail read-only 1 transaksi — dipakai link "Lihat Transaksi" dari
     * Detail PO (Bonus #1, Fase 2 PO Tools) untuk transaksi yang SUDAH
     * ter-link ke PO (referensi_type terisi), yang sebelumnya diarahkan ke
     * keuangan.edit dan SELALU ditolak (edit() menolak transaksi ber-
     * referensi). Reuse permission keuangan.view yang sudah ada — bukan
     * permission baru.
     */
    public function show(TransaksiKeuangan $transaksi)
    {
        abort_unless(auth()->user()->can('keuangan.view'), 403);

        $transaksi->load(['cabang', 'kas', 'kategoriDinamis', 'createdBy']);

        // referensi_type disimpan sebagai string logis, BUKAN FQCN — tidak ada
        // morphMap terdaftar, jadi morphTo() bawaan ($transaksi->referensi)
        // akan error kalau dipanggil langsung. Resolve manual, cuma utk kasus
        // 'purchase_order' yang relevan di halaman ini (link balik ke Detail PO).
        $referensiPo = null;
        if ($transaksi->referensi_type === 'purchase_order' && $transaksi->referensi_id) {
            $referensiPo = PurchaseOrder::withTrashed()->find($transaksi->referensi_id);
        }

        return view('keuangan.show', compact('transaksi', 'referensiPo'));
    }

    /**
     * Form edit transaksi manual (hanya untuk transaksi tanpa referensi)
     */
    public function edit(TransaksiKeuangan $transaksi)
    {
        abort_unless(auth()->user()->can('keuangan.edit'), 403);

        if ($transaksi->referensi_type) {
            return redirect()->route('keuangan.index')
                ->with('error', 'Transaksi ini tidak bisa diedit langsung. Edit dari sumber aslinya (Order / PO).');
        }

        $kass      = Kas::aktif()->where('cabang_id', $transaksi->cabang_id)->get();
        $tipes     = TipeTransaksiKeuangan::cases();
        $kategoris = KategoriModel::aktif()->orderBy('urutan')->orderBy('nama')->get();
        $kategoriPengeluarans = KategoriPengeluaran::cases();

        return view('keuangan.edit', compact('transaksi', 'kass', 'tipes', 'kategoris', 'kategoriPengeluarans'));
    }

    /**
     * Simpan perubahan transaksi manual — termasuk sync saldo kas
     */
    public function update(Request $request, TransaksiKeuangan $transaksi)
    {
        abort_unless(auth()->user()->can('keuangan.edit'), 403);

        if ($transaksi->referensi_type) {
            return back()->with('error', 'Transaksi ini tidak bisa diedit langsung.');
        }

        $request->validate([
            'tanggal_transaksi' => 'required|date',
            'tipe'              => 'required|in:' . implode(',', array_column(TipeTransaksiKeuangan::cases(), 'value')),
            'kategori_id'       => 'required|exists:kategori_transaksis,id',
            'kategori_pengeluaran' => 'required_if:tipe,' . TipeTransaksiKeuangan::Pengeluaran->value
                . '|nullable|in:' . implode(',', array_column(KategoriPengeluaran::cases(), 'value')),
            'keterangan'        => 'required|string|max:255',
            'jumlah'            => 'required|numeric|min:1',
            // Wajib pilih Kas — lihat catatan sama di store() (Bug 3).
            'kas_id'            => 'required|exists:kas,id',
            'catatan'           => 'nullable|string',
            'bukti'             => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'kas_id.required' => 'Kas wajib dipilih.',
        ]);

        $kategoriObj = KategoriModel::findOrFail($request->kategori_id);
        $nomor       = $transaksi->nomor_transaksi;
        $kategoriPengeluaran = $request->tipe === TipeTransaksiKeuangan::Pengeluaran->value
            ? $request->kategori_pengeluaran
            : null;

        // Handle upload bukti baru
        $buktiPath = $transaksi->bukti_path;
        if ($request->hasFile('bukti')) {
            if ($buktiPath) {
                Storage::disk('public')->delete($buktiPath);
            }
            $buktiPath = $request->file('bukti')->store('bukti_transaksi', 'public');
        }

        DB::transaction(function () use ($request, $transaksi, $kategoriObj, $kategoriPengeluaran, $buktiPath) {
            $oldJumlah = (float) $transaksi->jumlah;
            $oldTipe   = $transaksi->tipe;
            $oldKasId  = $transaksi->kas_id;

            $transaksi->update([
                'tanggal_transaksi' => $request->tanggal_transaksi,
                'tipe'              => $request->tipe,
                'kategori'          => $kategoriObj->toEnumValue(),
                'kategori_id'       => $kategoriObj->id,
                'kategori_pengeluaran' => $kategoriPengeluaran,
                'keterangan'        => $request->keterangan,
                'jumlah'            => $request->jumlah,
                'kas_id'            => $request->kas_id,
                'bukti_path'        => $buktiPath,
                'catatan'           => $request->catatan,
            ]);

            // Balik saldo kas lama
            if ($oldKasId) {
                $oldKas = Kas::find($oldKasId);
                if ($oldKas) {
                    if ($oldTipe === TipeTransaksiKeuangan::Pemasukan) {
                        $oldKas->decrement('saldo_sekarang', $oldJumlah);
                    } else {
                        $oldKas->increment('saldo_sekarang', $oldJumlah);
                    }
                }
            }

            // Terapkan saldo kas baru
            if ($request->kas_id) {
                $newKas = Kas::find($request->kas_id);
                if ($newKas) {
                    if ($request->tipe === TipeTransaksiKeuangan::Pemasukan->value) {
                        $newKas->increment('saldo_sekarang', (float) $request->jumlah);
                    } else {
                        $newKas->decrement('saldo_sekarang', (float) $request->jumlah);
                    }
                }
            }
        });

        return redirect()->route('keuangan.index')
            ->with('success', "Transaksi {$nomor} berhasil diperbarui.");
    }

    /**
     * Hapus (soft-delete) transaksi manual — termasuk reverse saldo kas
     */
    public function destroy(TransaksiKeuangan $transaksi)
    {
        abort_unless(auth()->user()->can('keuangan.delete'), 403);

        if ($transaksi->referensi_type) {
            return back()->with('error', "Transaksi ini berasal dari {$transaksi->referensi_type}. Hapus dari sumber aslinya.");
        }

        $nomor = $transaksi->nomor_transaksi;

        DB::transaction(function () use ($transaksi) {
            // Balik saldo kas sebelum dihapus
            if ($transaksi->kas_id) {
                $kas = Kas::find($transaksi->kas_id);
                if ($kas) {
                    if ($transaksi->tipe === TipeTransaksiKeuangan::Pemasukan) {
                        $kas->decrement('saldo_sekarang', (float) $transaksi->jumlah);
                    } else {
                        $kas->increment('saldo_sekarang', (float) $transaksi->jumlah);
                    }
                }
            }

            $transaksi->delete();
        });

        return redirect()->route('keuangan.index')
            ->with('success', "Transaksi {$nomor} berhasil dihapus. Bisa dipulihkan di <a href=\"" . route('trash.index', ['model' => 'transaksi_keuangans']) . '">Data Terhapus</a>.');
    }

    /**
     * Cleanup Tool B2 — assign Kas Sumber ke transaksi lama yang kas_id-nya
     * NULL (celah data sebelum kas_id wajib, Bug 3). Increment/decrement
     * saldo kas terkait sesuai tipe transaksi, sekali jalan saat pertama
     * kali di-assign — transaksi ini belum pernah menyentuh saldo kas
     * manapun sebelumnya jadi tidak ada balik saldo lama yang perlu dibalik.
     */
    public function assignKas(Request $request, TransaksiKeuangan $transaksi)
    {
        abort_unless(auth()->user()->can('transaksi.assign_kas.action'), 403);

        if ($transaksi->kas_id) {
            return back()->with('error', 'Transaksi ini sudah punya Kas Sumber.');
        }
        if ($transaksi->referensi_type) {
            return back()->with('error', 'Transaksi ini punya referensi sumber lain (' . $transaksi->referensi_type . '), tidak bisa di-assign kas manual di sini.');
        }

        $request->validate(['kas_id' => 'required|exists:kas,id'], [
            'kas_id.required' => 'Kas wajib dipilih.',
        ]);

        $kas = Kas::where('id', $request->kas_id)->where('cabang_id', $transaksi->cabang_id)->first();
        if (!$kas) {
            return back()->with('error', 'Kas tidak ditemukan atau bukan milik cabang transaksi ini.');
        }

        DB::transaction(function () use ($transaksi, $kas) {
            $transaksi->update(['kas_id' => $kas->id]);

            if ($transaksi->tipe === TipeTransaksiKeuangan::Pemasukan) {
                $kas->increment('saldo_sekarang', (float) $transaksi->jumlah);
            } else {
                $kas->decrement('saldo_sekarang', (float) $transaksi->jumlah);
            }
        });

        activity('TransaksiKeuangan')
            ->performedOn($transaksi)
            ->causedBy(auth()->user())
            ->withProperties([
                'kas_id'   => $kas->id,
                'nama_kas' => $kas->nama_kas,
                'jumlah'   => (float) $transaksi->jumlah,
            ])
            ->log(auth()->user()->name . " assign kas \"{$kas->nama_kas}\" ke transaksi {$transaksi->nomor_transaksi} (cleanup data historis)");

        return redirect()->route('keuangan.index')
            ->with('success', "Kas \"{$kas->nama_kas}\" berhasil di-assign ke transaksi {$transaksi->nomor_transaksi}.");
    }

    /**
     * Kelola daftar kas per cabang
     */
    public function kas(Request $request)
    {
        abort_unless(auth()->user()->can('keuangan.kas_view'), 403);

        $user = auth()->user();
        $activeCabangId = session('active_cabang_id');

        $query = Kas::with('cabang')->orderBy('cabang_id')->orderBy('nama_kas');

        if (!$user->canAccessAllBranches()) {
            $cabangId = $activeCabangId ?? $user->defaultCabangId();
            $query->where('cabang_id', $cabangId);
        } elseif ($activeCabangId) {
            $query->where('cabang_id', $activeCabangId);
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        $kass = $query->get();
        $cabangs = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        return view('keuangan.kas', compact('kass', 'cabangs'));
    }

    /**
     * Tambah kas baru
     */
    public function storeKas(Request $request)
    {
        abort_unless(auth()->user()->can('keuangan.kas_create'), 403);

        $request->validate([
            'nama_kas'        => 'required|string|max:100',
            'tipe_kas'        => 'required|in:tunai,bank',
            'nomor_rekening'  => 'nullable|string|max:30',
            'nama_bank'       => 'nullable|string|max:100',
            'saldo_awal'      => 'required|numeric|min:0',
            'saldo_minimum'   => 'nullable|numeric|min:0',
            'default_untuk'   => 'nullable|in:tunai,transfer,qris',
        ]);

        $user = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $cabangId = $activeCabangId ?? $user->defaultCabangId();

        // Cegah duplikasi default_untuk per cabang
        if ($request->filled('default_untuk')) {
            $duplicate = Kas::where('cabang_id', $cabangId)
                ->where('default_untuk', $request->default_untuk)
                ->exists();
            if ($duplicate) {
                return back()->withInput()->withErrors([
                    'default_untuk' => $this->pesanDuplikatDefaultUntuk($request->default_untuk),
                ]);
            }
        }

        DB::transaction(function () use ($request, $cabangId) {
            $kas = Kas::create([
                'cabang_id'      => $cabangId,
                'nama_kas'       => $request->nama_kas,
                'tipe_kas'       => $request->tipe_kas,
                'default_untuk'  => $request->default_untuk ?: null,
                'nomor_rekening' => $request->nomor_rekening,
                'nama_bank'      => $request->nama_bank,
                'saldo_awal'     => $request->saldo_awal,
                'saldo_sekarang' => $request->saldo_awal,
                'saldo_minimum'  => (float) str_replace('.', '', $request->saldo_minimum ?? '0'),
                'is_active'      => true,
            ]);

            // Jika ada saldo awal, catat sebagai TransaksiKeuangan agar masuk laporan
            if ((float) $request->saldo_awal > 0) {
                $prefixKas   = 'TRX-' . date('Ymd');
                $lastNomorKas = TransaksiKeuangan::withTrashed()
                    ->where('nomor_transaksi', 'like', $prefixKas . '-%')
                    ->orderByDesc('id')
                    ->value('nomor_transaksi');
                $seqKas = 1;
                if ($lastNomorKas && preg_match('/(\d+)$/', $lastNomorKas, $mk)) {
                    $seqKas = ((int) $mk[1]) + 1;
                }
                TransaksiKeuangan::create([
                    'cabang_id'         => $cabangId,
                    'kas_id'            => $kas->id,
                    'nomor_transaksi'   => $prefixKas . '-' . str_pad($seqKas, 4, '0', STR_PAD_LEFT),
                    'tanggal_transaksi' => date('Y-m-d'),
                    'tipe'              => TipeTransaksiKeuangan::Pemasukan,
                    'kategori'          => KategoriTransaksi::SaldoAwal,
                    'keterangan'        => 'Saldo awal kas: ' . $request->nama_kas,
                    'jumlah'            => $request->saldo_awal,
                    'referensi_type'    => 'kas',
                    'referensi_id'      => $kas->id,
                    'created_by'        => auth()->id(),
                ]);
            }
        });

        return redirect()->route('keuangan.kas')->with('success', 'Kas baru berhasil ditambahkan.');
    }

    /**
     * Pesan error yang jelas untuk duplikasi default_untuk — dipakai bareng
     * storeKas() dan updateKas() supaya konsisten.
     */
    private function pesanDuplikatDefaultUntuk(string $value): string
    {
        $label = match ($value) {
            'tunai'    => 'Tunai',
            'transfer' => 'Transfer Bank',
            'qris'     => 'QRIS',
            default    => $value,
        };

        return "Opsi \"{$label}\" sudah dipakai kas lain di cabang ini. Pilih opsi lain atau kosongkan.";
    }

    /**
     * Hapus (soft-delete) kas
     */
    public function destroyKas(Kas $kas)
    {
        abort_unless(auth()->user()->can('keuangan.kas_delete'), 403);

        if ($kas->saldo_sekarang != 0) {
            return back()->with('error',
                'Kas "' . $kas->nama_kas . '" masih memiliki saldo Rp ' .
                number_format($kas->saldo_sekarang, 0, ',', '.') . '. Nol-kan saldo dulu sebelum menghapus.');
        }

        $nama = $kas->nama_kas;
        $kas->delete();

        return redirect()->route('keuangan.kas')
            ->with('success', "Kas \"{$nama}\" berhasil dihapus. Bisa dipulihkan di <a href=\"" . route('trash.index', ['model' => 'kas']) . '">Data Terhapus</a>.');
    }

    /**
     * Log pembukaan laci kasir manual (dari tombol "Buka Laci" di POS)
     */
    public function logBukaLaci(Request $request)
    {
        abort_unless(auth()->user()->can('kas.buka_laci'), 403);

        $request->validate(['alasan' => 'required|string|max:255']);

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'alasan'    => $request->alasan,
                'cabang_id' => session('active_cabang_id'),
            ])
            ->log('Buka Laci Manual: ' . $request->alasan);

        return response()->json(['success' => true]);
    }

    /**
     * Update kas
     */
    public function updateKas(Request $request, Kas $kas)
    {
        abort_unless(auth()->user()->can('keuangan.kas_edit'), 403);

        $request->validate([
            'nama_kas'       => 'required|string|max:100',
            'tipe_kas'       => 'required|in:tunai,bank',
            'nomor_rekening' => 'nullable|string|max:30',
            'nama_bank'      => 'nullable|string|max:100',
            'is_active'      => 'boolean',
            'saldo_minimum'  => 'nullable|numeric|min:0',
            'default_untuk'  => 'nullable|in:tunai,transfer,qris',
        ]);

        // Cegah duplikasi default_untuk per cabang (kecuali kas ini sendiri)
        $newDefault = $request->input('default_untuk') ?: null;
        if ($newDefault && $newDefault !== $kas->default_untuk) {
            $duplicate = Kas::where('cabang_id', $kas->cabang_id)
                ->where('default_untuk', $newDefault)
                ->where('id', '!=', $kas->id)
                ->exists();
            if ($duplicate) {
                return back()->withInput()->withErrors([
                    'default_untuk' => $this->pesanDuplikatDefaultUntuk($newDefault),
                ]);
            }
        }

        $kas->update([
            'nama_kas'       => $request->nama_kas,
            'tipe_kas'       => $request->tipe_kas,
            'default_untuk'  => $newDefault,
            'nomor_rekening' => $request->nomor_rekening,
            'nama_bank'      => $request->nama_bank,
            'is_active'      => $request->boolean('is_active', true),
            'saldo_minimum'  => (float) str_replace('.', '', $request->saldo_minimum ?? '0'),
        ]);

        return redirect()->route('keuangan.kas')->with('success', 'Data kas berhasil diperbarui.');
    }

    /**
     * Cleanup Tool B3 — hitung berapa seharusnya saldo_sekarang kas ini
     * berdasarkan riwayat transaksi_keuangans, dua jalur formula supaya
     * tidak double-count saldo_awal (lihat catatan di hitungExpectedSaldoKas()).
     */
    public function previewSinkronSaldo(Kas $kas)
    {
        abort_unless(auth()->user()->can('kas.sinkron_saldo.action'), 403);

        $expected = $this->hitungExpectedSaldoKas($kas);
        $saldoSekarang = (float) $kas->saldo_sekarang;

        return response()->json([
            'saldo_sekarang' => $saldoSekarang,
            'expected_saldo' => $expected,
            'selisih'        => $saldoSekarang - $expected,
        ]);
    }

    /**
     * Cleanup Tool B3 — terapkan hasil hitungan preview ke saldo_sekarang.
     */
    public function sinkronSaldo(Kas $kas)
    {
        abort_unless(auth()->user()->can('kas.sinkron_saldo.action'), 403);

        $saldoLama = (float) $kas->saldo_sekarang;
        $expected  = $this->hitungExpectedSaldoKas($kas);

        $kas->update(['saldo_sekarang' => $expected]);

        activity('Kas')
            ->performedOn($kas)
            ->causedBy(auth()->user())
            ->withProperties([
                'saldo_lama' => $saldoLama,
                'saldo_baru' => $expected,
                'selisih'    => $saldoLama - $expected,
            ])
            ->log(auth()->user()->name . " sinkron saldo kas \"{$kas->nama_kas}\" dari Rp "
                . number_format($saldoLama, 0, ',', '.') . ' ke Rp ' . number_format($expected, 0, ',', '.')
                . ' (cleanup data historis)');

        return redirect()->route('keuangan.kas')
            ->with('success', "Saldo kas \"{$kas->nama_kas}\" berhasil disinkron: Rp "
                . number_format($saldoLama, 0, ',', '.') . ' → Rp ' . number_format($expected, 0, ',', '.') . '.');
    }

    /**
     * Formula rekonsiliasi dual-path (audit produksi 2026-07-26, Bug Bonus):
     * - Kas yang dibuat via storeKas() SEKARANG SUDAH otomatis membuat
     *   TransaksiKeuangan "Saldo awal kas" (referensi_type='kas', referensi_id=kas->id)
     *   saat kas dibuat — kalau transaksi itu ADA, saldo_awal SUDAH termasuk
     *   dalam net(semua transaksi kas ini), jadi TIDAK BOLEH ditambah lagi.
     * - Kas legacy (dibuat sebelum logic itu ada, mis. id=3 & id=16 hasil
     *   audit) TIDAK punya transaksi itu — saldo_awal harus ditambah manual
     *   supaya tidak hilang dari perhitungan.
     */
    private function hitungExpectedSaldoKas(Kas $kas): float
    {
        $adaSaldoAwalTrx = TransaksiKeuangan::where('kas_id', $kas->id)
            ->where('referensi_type', 'kas')
            ->where('referensi_id', $kas->id)
            ->exists();

        $net = (float) TransaksiKeuangan::where('kas_id', $kas->id)
            ->selectRaw('SUM(CASE WHEN tipe = ? THEN jumlah ELSE -jumlah END) as net', [TipeTransaksiKeuangan::Pemasukan->value])
            ->value('net');

        return $adaSaldoAwalTrx ? $net : ((float) $kas->saldo_awal + $net);
    }

    /**
     * Laporan laba rugi per periode
     */
    public function laporan(Request $request)
    {
        abort_unless(auth()->user()->can('laporan.keuangan.view'), 403);

        $user = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $periode = $request->get('periode', date('Y-m'));

        $query = TransaksiKeuangan::query()
            ->where('tanggal_transaksi', 'like', $periode . '%');

        // Filter cabang
        if (!$user->canAccessAllBranches()) {
            $cabangId = $activeCabangId ?? $user->defaultCabangId();
            $query->where('cabang_id', $cabangId);
            $cabangNama = Cabang::find($cabangId)?->nama_cabang ?? '-';
        } elseif ($activeCabangId) {
            $query->where('cabang_id', $activeCabangId);
            $cabangNama = Cabang::find($activeCabangId)?->nama_cabang ?? 'Semua Cabang';
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
            $cabangNama = Cabang::find($request->cabang_id)?->nama_cabang ?? '-';
        } else {
            $cabangNama = 'Semua Cabang';
        }

        $transaksis = $query->with('kategoriDinamis')->get();

        $labelKategori = fn ($t) => $t->kategoriDinamis?->nama ?? $t->kategori?->label() ?? 'Lainnya';

        // Group pemasukan by kategori
        $pemasukan = $transaksis->where('tipe', TipeTransaksiKeuangan::Pemasukan)
            ->groupBy($labelKategori)
            ->map(fn($group) => $group->sum('jumlah'));

        // Group pengeluaran by kategori
        $pengeluaran = $transaksis->where('tipe', TipeTransaksiKeuangan::Pengeluaran)
            ->groupBy($labelKategori)
            ->map(fn($group) => $group->sum('jumlah'));

        $totalPemasukan = $pemasukan->sum();
        $totalPengeluaran = $pengeluaran->sum();
        $labaRugi = $totalPemasukan - $totalPengeluaran;

        $cabangs = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        return view('keuangan.laporan', compact(
            'pemasukan', 'pengeluaran', 'totalPemasukan', 'totalPengeluaran',
            'labaRugi', 'periode', 'cabangNama', 'cabangs'
        ));
    }
}
