<?php

namespace App\Http\Controllers;

use App\Models\MemberAkun;
use App\Models\OpnameMember;
use App\Services\LaporanOnlineService;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;
use App\Models\StokMember;
use App\Models\StokMemberMutasi;
use Illuminate\Http\Request;

/**
 * ERP BABA Tahap 2c — Toko Online › Stok Member: pantau stok parfum milik member (hanya lihat;
 * stok berubah otomatis dari pesanan / retur). Izin toko_online.kelola.
 */
class StokMemberController extends Controller
{
    private function izin(): void
    {
        abort_unless(auth()->user()->can('toko_online.kelola'), 403);
    }

    public function index(Request $request)
    {
        $this->izin();
        $cari = trim((string) $request->query('cari', ''));
        $memberIds = $cari === '' ? null : MemberAkun::where('nama', 'like', "%{$cari}%")->orWhere('no_id', 'like', "{$cari}%")
            ->orWhere('no_member', $cari)->pluck('member_id');

        $baris = StokMember::query()
            ->selectRaw('member_id, COUNT(*) AS jenis, SUM(qty) AS total_qty, SUM(qty * nilai_rata) AS total_nilai')
            ->where('qty', '>', 0)
            ->when($memberIds, fn ($q) => $q->whereIn('member_id', $memberIds))
            ->groupBy('member_id')->orderByDesc('total_qty')
            ->paginate(25)->withQueryString();
        $akun = MemberAkun::whereIn('member_id', collect($baris->items())->pluck('member_id'))->get()->keyBy('member_id');

        return view('toko-online.stok-member.index', compact('baris', 'akun', 'cari'));
    }

    /**
     * ERP BABA Tahap 7f — rekap stok opname member (pantau saja): per member atau per tim (upline pada level jabatan,
     * dari struktur aplikasi member), periode bulan. Aplikasi member mati → rekap per tim diganti pesan.
     */
    public function opname(Request $request, LaporanOnlineService $lap)
    {
        $this->izin();
        try {
            $dari = Carbon::parse($request->query('dari', now()->startOfMonth()->toDateString()))->startOfDay();
            $sampai = Carbon::parse($request->query('sampai', now()->toDateString()))->endOfDay();
        } catch (Throwable $e) {
            [$dari, $sampai] = [now()->startOfMonth(), now()->endOfDay()];
        }
        if ($dari->gt($sampai) || $dari->diffInDays($sampai) > 370) {
            [$dari, $sampai] = [now()->startOfMonth(), now()->endOfDay()];
        }
        $level = array_key_exists((int) $request->query('level'), LaporanOnlineService::LEVEL_TIM) ? (int) $request->query('level') : null;
        $opname = OpnameMember::with(['item', 'member'])->whereBetween('created_at', [$dari, $sampai])->orderByDesc('id')->get();
        $perMember = $opname->groupBy('member_id')->map(fn ($g) => ['member_id' => $g->first()->member_id, 'akun' => $g->first()->member, 'kali' => $g->count(),
            'botol' => (int) $g->sum(fn ($o) => $o->item->sum('qty')), 'nilai' => round((float) $g->sum('total_nilai'), 2),
            'penyebab' => $g->flatMap->item->groupBy('penyebab')->map(fn ($x) => (int) $x->sum('qty'))->all()])->sortByDesc('nilai')->values();
        $perTim = null;
        $galat = null;
        if ($level) {
            try {
                $tim = $lap->timUntuk($perMember->pluck('member_id')->all(), $level);
                $perTim = $perMember->groupBy(fn ($r) => $tim[$r['member_id']]['kunci'])->map(fn ($g) => ['nama' => $tim[$g->first()['member_id']]['label'],
                    'anggota' => $g->count(), 'kali' => $g->sum('kali'), 'botol' => $g->sum('botol'), 'nilai' => round($g->sum('nilai'), 2)])->sortByDesc('nilai')->values();
            } catch (RuntimeException $e) {
                $galat = $e->getMessage();
            }
        }

        return view('toko-online.stok-member.opname', compact('opname', 'perMember', 'perTim', 'galat', 'level', 'dari', 'sampai'));
    }

    public function show(int $memberId)
    {
        $this->izin();
        $akun = MemberAkun::where('member_id', $memberId)->firstOrFail();

        return view('toko-online.stok-member.show', [
            'akun'   => $akun,
            'stok'   => StokMember::with('item')->where('member_id', $memberId)->orderByDesc('qty')->get(),
            'mutasi' => StokMemberMutasi::with('item')->where('member_id', $memberId)->orderByDesc('id')->paginate(50),
            'opname' => OpnameMember::with('item')->where('member_id', $memberId)->orderByDesc('id')->limit(20)->get(),
        ]);
    }
}
