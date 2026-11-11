<?php

namespace App\Services;

use App\Models\LoyaltyKlaim;
use App\Models\LoyaltyProgram;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Program Loyalty Fase 2 (event-based) — workflow klaim manual: kasir input
 * klaim + bukti, Owner/Admin Pusat approve/reject, lalu tandai voucher sudah
 * diberikan (issued). Beda karakter dari LoyaltyService (Fase 1, auto-track
 * pasif) -- service ini murni transisi status, tidak ada perhitungan kg.
 */
class LoyaltyKlaimService
{
    /**
     * Buat klaim baru — guard "1 pelanggan cuma 1x per program event_based":
     * kalau pelanggan SUDAH punya klaim berstatus pending/approved/issued
     * (belum ditolak) utk program yang sama, tolak. Klaim yang REJECTED
     * TIDAK menghalangi klaim baru — kasir/pelanggan boleh coba lagi kalau
     * bukti sebelumnya tidak valid (bukan "jatah" yang habis terpakai).
     */
    public function buatKlaim(int $pelangganId, int $programId, array $data): LoyaltyKlaim
    {
        $program = LoyaltyProgram::findOrFail($programId);

        if ($program->tipe_program !== 'event_based') {
            throw ValidationException::withMessages([
                'loyalty_program_id' => "Program \"{$program->nama}\" bukan program event-based, tidak bisa diklaim manual.",
            ]);
        }

        $sudahKlaim = LoyaltyKlaim::where('pelanggan_id', $pelangganId)
            ->where('loyalty_program_id', $programId)
            ->whereIn('status', ['pending', 'approved', 'issued'])
            ->exists();

        if ($sudahKlaim) {
            throw ValidationException::withMessages([
                'pelanggan_id' => "Pelanggan ini sudah pernah klaim program \"{$program->nama}\" (1x per pelanggan).",
            ]);
        }

        return LoyaltyKlaim::create([
            'loyalty_program_id' => $programId,
            'pelanggan_id'       => $pelangganId,
            'order_id'           => $data['order_id'] ?? null,
            'bukti_url'          => $data['bukti_url'],
            'bukti_catatan'      => $data['bukti_catatan'] ?? null,
            'status'             => 'pending',
        ]);
    }

    public function approve(LoyaltyKlaim $klaim, float $nominalVoucher, int $userId): LoyaltyKlaim
    {
        if ($klaim->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Klaim ini sudah diproses sebelumnya (status: ' . $klaim->status . ').',
            ]);
        }

        return DB::transaction(function () use ($klaim, $nominalVoucher, $userId) {
            $klaim->update([
                'status'               => 'approved',
                'nominal_voucher'      => $nominalVoucher,
                'approved_by_user_id'  => $userId,
                'approved_at'          => now(),
            ]);

            return $klaim->fresh();
        });
    }

    public function reject(LoyaltyKlaim $klaim, string $reason): LoyaltyKlaim
    {
        if ($klaim->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Klaim ini sudah diproses sebelumnya (status: ' . $klaim->status . ').',
            ]);
        }

        $klaim->update([
            'status'           => 'rejected',
            'rejected_reason'  => $reason,
        ]);

        return $klaim->fresh();
    }

    public function markIssued(LoyaltyKlaim $klaim, ?string $catatan = null): LoyaltyKlaim
    {
        if ($klaim->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Voucher cuma bisa ditandai "diberikan" dari klaim yang sudah approved (status sekarang: ' . $klaim->status . ').',
            ]);
        }

        $klaim->update([
            'status'          => 'issued',
            'issued_at'       => now(),
            'catatan_issued'  => $catatan,
        ]);

        return $klaim->fresh();
    }

    /**
     * Widget Dashboard "Klaim Menunggu Approval" — murni tambahan, TIDAK
     * di-scope cabang (klaim loyalty lintas cabang by design, sama seperti
     * LoyaltyService::getWidgetData()).
     */
    public function getWidgetData(int $maxRows = 5): array
    {
        return [
            'jumlah_pending' => LoyaltyKlaim::pending()->count(),
            'klaim_terbaru'  => LoyaltyKlaim::pending()
                ->with(['pelanggan', 'loyaltyProgram'])
                ->orderBy('created_at')
                ->take($maxRows)
                ->get(),
        ];
    }
}
