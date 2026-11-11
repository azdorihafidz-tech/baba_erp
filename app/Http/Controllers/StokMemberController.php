<?php

namespace App\Http\Controllers;

use App\Models\MemberAkun;
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

    public function show(int $memberId)
    {
        $this->izin();
        $akun = MemberAkun::where('member_id', $memberId)->firstOrFail();

        return view('toko-online.stok-member.show', [
            'akun'   => $akun,
            'stok'   => StokMember::with('item')->where('member_id', $memberId)->orderByDesc('qty')->get(),
            'mutasi' => StokMemberMutasi::with('item')->where('member_id', $memberId)->orderByDesc('id')->paginate(50),
        ]);
    }
}
