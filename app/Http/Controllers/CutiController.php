<?php

namespace App\Http\Controllers;

use App\Http\Requests\CutiRequest;
use App\Models\Absensi;
use App\Models\Cabang;
use App\Models\Cuti;
use App\Models\Karyawan;
use App\Models\SaldoCuti;
use App\Notifications\CutiNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CutiController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('cuti.view'), 403);

        $authUser = auth()->user();
        $query = Cuti::with(['karyawan', 'cabang', 'approvedBy'])
            ->withoutGlobalScope(\App\Models\Scopes\CabangScope::class);

        // Filter cabang
        if (!$authUser->canAccessAllBranches()) {
            $query->where('cabang_id', session('active_cabang_id'));
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter karyawan
        if ($request->filled('karyawan_id')) {
            $query->where('karyawan_id', $request->karyawan_id);
        }

        // Search multi-field — TAMBAHAN, tidak mengganti filter cabang/status/karyawan di atas
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('alasan', 'like', "%{$search}%")
                  ->orWhereHas('karyawan', fn ($k) => $k->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('nik', 'like', "%{$search}%"));
            });
        }

        $cutis   = $query->orderByDesc('tanggal_mulai')->paginate(15)->withQueryString();
        $cabangs = Cabang::aktif()->orderBy('nama_cabang')->get();
        $karyawans = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')->get();

        return view('cuti.index', compact('cutis', 'cabangs', 'karyawans'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('cuti.create'), 403);

        $cabangId  = session('active_cabang_id');
        $karyawans = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('cabang_id', $cabangId)
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')->get();

        return view('cuti.create', compact('karyawans'));
    }

    public function store(CutiRequest $request)
    {
        abort_unless(auth()->user()->can('cuti.create'), 403);

        $data = $request->validated();
        $data['cabang_id'] = session('active_cabang_id');

        $mulai   = Carbon::parse($data['tanggal_mulai']);
        $selesai = Carbon::parse($data['tanggal_selesai']);
        $data['jumlah_hari'] = $mulai->diffInDays($selesai) + 1;
        $data['status'] = 'pending';

        $cuti = Cuti::create($data);

        // Notifikasi ke Manajer Cabang
        $cabangId = $data['cabang_id'] ?? session('active_cabang_id');
        if ($cabangId) {
            $manajer = NotificationService::getManajerCabang($cabangId);
            NotificationService::send($manajer, new CutiNotification($cuti, 'pengajuan'));
        }

        return redirect()->route('cuti.index')
            ->with('success', 'Pengajuan cuti berhasil disubmit, menunggu persetujuan.');
    }

    public function edit(Cuti $cuti)
    {
        abort_unless(auth()->user()->can('cuti.create'), 403);

        if ($cuti->status !== 'pending') {
            return redirect()->route('cuti.index')
                ->with('error', 'Cuti yang sudah diproses tidak bisa diedit.');
        }

        $karyawans = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')->get();

        return view('cuti.edit', compact('cuti', 'karyawans'));
    }

    public function update(CutiRequest $request, Cuti $cuti)
    {
        abort_unless(auth()->user()->can('cuti.create'), 403);

        if ($cuti->status !== 'pending') {
            return redirect()->route('cuti.index')
                ->with('error', 'Cuti yang sudah diproses tidak bisa diedit.');
        }

        $data = $request->validated();
        $mulai   = Carbon::parse($data['tanggal_mulai']);
        $selesai = Carbon::parse($data['tanggal_selesai']);
        $data['jumlah_hari'] = $mulai->diffInDays($selesai) + 1;

        $cuti->update($data);

        return redirect()->route('cuti.index')
            ->with('success', 'Pengajuan cuti berhasil diperbarui.');
    }

    public function destroy(Cuti $cuti)
    {
        abort_unless(auth()->user()->can('cuti.delete'), 403);

        if ($cuti->status !== 'pending') {
            return back()->with('error', 'Hanya pengajuan yang masih pending yang bisa dihapus.');
        }

        $cuti->delete();

        return redirect()->route('cuti.index')
            ->with('success', 'Pengajuan cuti berhasil dihapus.');
    }

    /**
     * Setujui pengajuan cuti
     */
    public function approve(Cuti $cuti)
    {
        abort_unless(auth()->user()->can('cuti.approve'), 403);

        if ($cuti->status !== 'pending') {
            return back()->with('error', 'Pengajuan ini tidak bisa disetujui.');
        }

        DB::transaction(function () use ($cuti) {
            $cuti->update([
                'status'           => 'disetujui',
                'approved_by'      => auth()->id(),
                'approved_at'      => now(),
                'catatan_approver' => request('catatan_approver'),
            ]);

            // Update saldo cuti (untuk tipe cuti_tahunan)
            if ($cuti->tipe === 'cuti_tahunan') {
                $tahun    = $cuti->tanggal_mulai->year;
                $saldo    = SaldoCuti::firstOrCreate(
                    ['karyawan_id' => $cuti->karyawan_id, 'tahun' => $tahun],
                    ['saldo_awal' => 12, 'terpakai' => 0, 'sisa' => 12]
                );
                $saldo->increment('terpakai', $cuti->jumlah_hari);
                $saldo->update(['sisa' => $saldo->saldo_awal - $saldo->terpakai]);
            }

            // Update absensi terkait jadi 'cuti'
            $tanggalList = CarbonPeriod::create($cuti->tanggal_mulai, $cuti->tanggal_selesai);
            foreach ($tanggalList as $tgl) {
                if ($tgl->isWeekday()) {
                    Absensi::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
                        ->updateOrCreate(
                            ['karyawan_id' => $cuti->karyawan_id, 'tanggal' => $tgl->format('Y-m-d')],
                            [
                                'cabang_id'    => $cuti->cabang_id,
                                'status'       => 'cuti',
                                'keterangan'   => 'Cuti disetujui',
                                'dicatat_oleh' => auth()->id(),
                            ]
                        );
                }
            }
        });

        // Notifikasi ke karyawan yang mengajukan cuti
        if ($cuti->karyawan?->user_id) {
            $karyawanUser = \App\Models\User::find($cuti->karyawan->user_id);
            if ($karyawanUser) {
                NotificationService::send(collect([$karyawanUser]), new CutiNotification($cuti, 'disetujui'));
            }
        }

        return back()->with('success', 'Pengajuan cuti berhasil disetujui.');
    }

    /**
     * Tolak pengajuan cuti
     */
    public function tolak(Cuti $cuti)
    {
        abort_unless(auth()->user()->can('cuti.approve'), 403);

        if ($cuti->status !== 'pending') {
            return back()->with('error', 'Pengajuan ini tidak bisa ditolak.');
        }

        $cuti->update([
            'status'           => 'ditolak',
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
            'catatan_approver' => request('catatan_approver'),
        ]);

        // Notifikasi ke karyawan
        if ($cuti->karyawan?->user_id) {
            $karyawanUser = \App\Models\User::find($cuti->karyawan->user_id);
            if ($karyawanUser) {
                NotificationService::send(collect([$karyawanUser]), new CutiNotification($cuti, 'ditolak'));
            }
        }

        return back()->with('success', 'Pengajuan cuti berhasil ditolak.');
    }
}
