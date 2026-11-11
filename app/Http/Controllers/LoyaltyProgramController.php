<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyKlaim;
use App\Models\LoyaltyPencapaian;
use App\Models\LoyaltyProgram;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;

/**
 * Master Data → Program Loyalty. Murni CRUD program + lihat progress
 * pelanggan (LoyaltyService) — tidak menyentuh PenjualanService/orders
 * sama sekali (READ-ONLY terhadap data transaksi).
 */
class LoyaltyProgramController extends Controller
{
    /** Tahap 7 D'mentai — satuan tampilan turunan dari basis sumber_data. */
    private const SATUAN_PER_SUMBER = [
        'orders.berat_daging_kg' => 'kg',
        'orders.total_bayar' => 'Rp',
        'orders.count' => 'transaksi',
    ];

    public function index()
    {
        abort_unless(auth()->user()->can('loyalty.view'), 403);

        $programs = LoyaltyProgram::withCount('pencapaian')->orderByDesc('created_at')->paginate(20);

        return view('loyalty-program.index', compact('programs'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('loyalty.manage'), 403);

        return view('loyalty-program.create');
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('loyalty.manage'), 403);

        $validated = $request->validate([
            'nama'             => 'required|string|max:150',
            'tipe_program'     => 'required|in:auto_track,event_based',
            'deskripsi'        => 'nullable|string',
            // target_qty_kg cuma wajib utk auto_track (progress kg) — event_based
            // tidak punya target kg, cukup diklaim manual per pelanggan.
            'target_qty_kg'    => 'required_if:tipe_program,auto_track|nullable|numeric|min:0.01',
            // Tahap 7 D'mentai — 'penjualan' ditambah (D'mentai tidak py jasa
            // giling), 'jasa_giling' dipertahankan (backward compat data lama).
            'tipe_item'        => 'required|in:jasa_giling,penjualan',
            // Basis perhitungan progress — sengaja opsional (default Total
            // Belanja Rp, basis yang genuinely relevan utk retail D'mentai;
            // bug fix 2026-09-21 — dulu default kg warisan Berkah Mulyo)
            // supaya request yang belum tahu field ini tetap jalan tanpa error.
            'sumber_data'      => 'nullable|in:orders.berat_daging_kg,orders.total_bayar,orders.count',
            'periode_mulai'    => 'nullable|date',
            'periode_akhir'    => 'nullable|date|after_or_equal:periode_mulai',
            'hadiah'           => 'required|string',
            'nominal_voucher'  => 'nullable|numeric|min:0',
            'berulang'         => 'nullable|boolean',
            'status'           => 'required|in:aktif,nonaktif',
        ]);
        $validated['berulang'] = $request->boolean('berulang');
        $validated['sumber_data'] = $validated['sumber_data'] ?? 'orders.total_bayar';
        $validated['satuan_qty'] = self::SATUAN_PER_SUMBER[$validated['sumber_data']];
        // event_based tidak punya target kg — set 0 (kolom NOT NULL di DB)
        // supaya tidak error, tapi tidak dipakai kalkulasi apapun.
        if ($validated['tipe_program'] === 'event_based') {
            $validated['target_qty_kg'] = 0;
        }

        $program = LoyaltyProgram::create($validated);

        return redirect()->route('loyalty-program.show', $program)
            ->with('success', "Program \"{$program->nama}\" berhasil dibuat.");
    }

    public function show(LoyaltyProgram $loyaltyProgram, LoyaltyService $service)
    {
        abort_unless(auth()->user()->can('loyalty.view'), 403);

        // 2 tipe program punya tampilan Detail yang beda total (progress kg
        // vs daftar klaim event) — cuma hitung yang relevan, supaya tidak
        // panggil LoyaltyService utk program event_based (target_qty_kg=0,
        // konsep "progress" tidak berlaku di situ).
        $progressSemua = collect();
        $pencapaianList = collect();
        $klaims = collect();

        if ($loyaltyProgram->tipe_program === 'auto_track') {
            $progressSemua = $service->hitungProgressSemuaPelanggan($loyaltyProgram->id);
            $pencapaianList = LoyaltyPencapaian::with('pelanggan')
                ->where('loyalty_program_id', $loyaltyProgram->id)
                ->orderByDesc('tanggal_tercapai')
                ->get()
                ->keyBy('pelanggan_id');
        } else {
            $klaims = LoyaltyKlaim::with(['pelanggan', 'approvedBy'])
                ->where('loyalty_program_id', $loyaltyProgram->id)
                ->orderByDesc('created_at')
                ->get();
        }

        return view('loyalty-program.show', compact('loyaltyProgram', 'progressSemua', 'pencapaianList', 'klaims'));
    }

    public function edit(LoyaltyProgram $loyaltyProgram)
    {
        abort_unless(auth()->user()->can('loyalty.manage'), 403);

        return view('loyalty-program.edit', compact('loyaltyProgram'));
    }

    public function update(Request $request, LoyaltyProgram $loyaltyProgram)
    {
        abort_unless(auth()->user()->can('loyalty.manage'), 403);

        $validated = $request->validate([
            'nama'             => 'required|string|max:150',
            'tipe_program'     => 'required|in:auto_track,event_based',
            'deskripsi'        => 'nullable|string',
            'target_qty_kg'    => 'required_if:tipe_program,auto_track|nullable|numeric|min:0.01',
            'tipe_item'        => 'required|in:jasa_giling,penjualan',
            'sumber_data'      => 'nullable|in:orders.berat_daging_kg,orders.total_bayar,orders.count',
            'periode_mulai'    => 'nullable|date',
            'periode_akhir'    => 'nullable|date|after_or_equal:periode_mulai',
            'hadiah'           => 'required|string',
            'nominal_voucher'  => 'nullable|numeric|min:0',
            'berulang'         => 'nullable|boolean',
            'status'           => 'required|in:aktif,nonaktif',
        ]);
        $validated['berulang'] = $request->boolean('berulang');
        if (isset($validated['sumber_data'])) {
            $validated['satuan_qty'] = self::SATUAN_PER_SUMBER[$validated['sumber_data']];
        }
        if ($validated['tipe_program'] === 'event_based') {
            $validated['target_qty_kg'] = 0;
        }

        $loyaltyProgram->update($validated);

        return redirect()->route('loyalty-program.show', $loyaltyProgram)
            ->with('success', "Program \"{$loyaltyProgram->nama}\" berhasil diperbarui.");
    }

    /**
     * Tandai 1 pencapaian sudah diberikan hadiahnya. Kalau pencapaian belum
     * ada (pelanggan baru saja tercapai tapi cekPencapaianBaru() belum
     * sempat jalan), buat dulu sebelum ditandai — supaya Owner tidak perlu
     * menunggu scheduler harian utk aksi manual dari halaman ini.
     */
    public function tandaiHadiah(Request $request, LoyaltyProgram $loyaltyProgram, LoyaltyService $service)
    {
        abort_unless(auth()->user()->can('loyalty.manage'), 403);

        $validated = $request->validate([
            'pelanggan_id'   => 'required|exists:pelanggans,id',
            'catatan_hadiah' => 'nullable|string|max:255',
        ]);

        $progress = $service->hitungProgressPelanggan($validated['pelanggan_id'], $loyaltyProgram->id);
        abort_unless($progress['tercapai'], 422, 'Pelanggan belum mencapai target program ini.');

        $pencapaian = LoyaltyPencapaian::firstOrCreate(
            [
                'pelanggan_id'       => $validated['pelanggan_id'],
                'loyalty_program_id' => $loyaltyProgram->id,
            ],
            [
                'tanggal_tercapai' => now()->toDateString(),
                'progress_kg'      => $progress['total_kg'],
                'status'           => 'tercapai',
            ]
        );

        $pencapaian->update([
            'status'         => 'hadiah_diberikan',
            'catatan_hadiah' => $validated['catatan_hadiah'] ?? ('Hadiah diberikan ' . now()->translatedFormat('d M Y')),
        ]);

        return back()->with('success', 'Hadiah berhasil ditandai sebagai sudah diberikan.');
    }
}
