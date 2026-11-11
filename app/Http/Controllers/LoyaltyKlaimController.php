<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyKlaim;
use App\Models\LoyaltyProgram;
use App\Models\Pelanggan;
use App\Services\LoyaltyKlaimService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Program Loyalty Fase 2 (event-based) — workflow klaim manual: kasir input
 * klaim + bukti (link sosmed atau upload foto), Owner/Admin Pusat
 * approve/reject/tandai voucher diberikan. Transisi status murni lewat
 * LoyaltyKlaimService, controller ini cuma validasi input + wiring.
 */
class LoyaltyKlaimController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('loyalty.klaim.view'), 403);

        $query = LoyaltyKlaim::with(['pelanggan', 'loyaltyProgram', 'approvedBy'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('loyalty_program_id')) {
            $query->where('loyalty_program_id', $request->loyalty_program_id);
        }

        $klaims = $query->paginate(20)->withQueryString();
        $programs = LoyaltyProgram::eventBased()->orderBy('nama')->get();

        return view('loyalty-klaim.index', compact('klaims', 'programs'));
    }

    public function create(Request $request)
    {
        abort_unless(auth()->user()->can('loyalty.klaim.buat'), 403);

        $programs = LoyaltyProgram::eventBased()->where('status', 'aktif')->orderBy('nama')->get();
        $pelanggans = Pelanggan::orderBy('nama_pelanggan')->get();

        $selectedProgramId = $request->get('loyalty_program_id');
        $selectedPelangganId = $request->get('pelanggan_id');
        $orderId = $request->get('order_id');

        return view('loyalty-klaim.create', compact(
            'programs', 'pelanggans', 'selectedProgramId', 'selectedPelangganId', 'orderId'
        ));
    }

    public function store(Request $request, LoyaltyKlaimService $service)
    {
        abort_unless(auth()->user()->can('loyalty.klaim.buat'), 403);

        $validated = $request->validate([
            'loyalty_program_id' => 'required|exists:loyalty_programs,id',
            'pelanggan_id'       => 'required|exists:pelanggans,id',
            'order_id'           => 'nullable|exists:orders,id',
            'bukti_link'         => 'nullable|url|max:500',
            'bukti_file'         => 'nullable|image|max:5120',
            'bukti_catatan'      => 'nullable|string|max:255',
        ]);

        // Bukti bisa link sosmed ATAU upload foto — minimal salah satu wajib
        // diisi (disetujui Owner: terima keduanya, bukan salah satu format saja).
        if (empty($validated['bukti_link']) && !$request->hasFile('bukti_file')) {
            return back()->withErrors([
                'bukti_link' => 'Wajib isi Link Bukti ATAU upload Foto Bukti (minimal salah satu).',
            ])->withInput();
        }

        $buktiUrl = $validated['bukti_link'] ?? null;
        if ($request->hasFile('bukti_file')) {
            // Upload foto diprioritaskan kalau kasir isi keduanya sekaligus.
            $buktiUrl = $request->file('bukti_file')->store('bukti_klaim_loyalty', 'public');
        }

        try {
            $service->buatKlaim($validated['pelanggan_id'], $validated['loyalty_program_id'], [
                'order_id'      => $validated['order_id'] ?? null,
                'bukti_url'     => $buktiUrl,
                'bukti_catatan' => $validated['bukti_catatan'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('loyalty-klaim.index')
            ->with('success', 'Klaim berhasil diajukan, menunggu approval Owner/Admin Pusat.');
    }

    public function approve(Request $request, LoyaltyKlaim $loyaltyKlaim, LoyaltyKlaimService $service)
    {
        abort_unless(auth()->user()->can('loyalty.klaim.approve'), 403);

        $validated = $request->validate([
            'nominal_voucher' => 'required|numeric|min:0',
        ]);

        try {
            $service->approve($loyaltyKlaim, (float) $validated['nominal_voucher'], auth()->id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Klaim disetujui.');
    }

    public function reject(Request $request, LoyaltyKlaim $loyaltyKlaim, LoyaltyKlaimService $service)
    {
        abort_unless(auth()->user()->can('loyalty.klaim.reject'), 403);

        $validated = $request->validate([
            'rejected_reason' => 'required|string|max:255',
        ]);

        try {
            $service->reject($loyaltyKlaim, $validated['rejected_reason']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Klaim ditolak.');
    }

    public function markIssued(Request $request, LoyaltyKlaim $loyaltyKlaim, LoyaltyKlaimService $service)
    {
        abort_unless(auth()->user()->can('loyalty.klaim.issued'), 403);

        $validated = $request->validate([
            'catatan_issued' => 'nullable|string|max:255',
        ]);

        try {
            $service->markIssued($loyaltyKlaim, $validated['catatan_issued'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Voucher berhasil ditandai sudah diberikan.');
    }
}
