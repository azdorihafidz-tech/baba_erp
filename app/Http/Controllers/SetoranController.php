<?php

namespace App\Http\Controllers;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\StatusSetoran;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Cabang;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\Scopes\CabangScope;
use App\Models\TransaksiKeuangan;
use App\Notifications\SetoranNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SetoranController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('setoran.view'), 403);

        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $tab            = $request->input('tab', 'semua');

        // Base: hanya SETOR-OUT (satu record per setoran, hindari duplikasi)
        // withoutGlobalScope(CabangScope::class) -- BUKAN withoutGlobalScopes()
        // tanpa argumen -- supaya SoftDeletingScope tetap aktif (Bug 1 pattern,
        // audit produksi 2026-07-27: setoran yang sudah dihapus tetap tampil di list).
        $baseQuery = TransaksiKeuangan::withoutGlobalScope(CabangScope::class)
            ->whereHas('kategoriDinamis', fn($q) => $q->where('kode', 'SETOR-OUT'));

        if (!$user->canAccessAllBranches()) {
            $cabangId = $activeCabangId ?? $user->defaultCabangId();
            $baseQuery->where('cabang_id', $cabangId);
        }

        // Filter cabang asal (Owner only via request param)
        if ($request->filled('cabang_id') && $user->canAccessAllBranches()) {
            $baseQuery->where('cabang_id', $request->cabang_id);
        }

        // Count per status (satu query)
        $countRow = (clone $baseQuery)->selectRaw("
            SUM(CASE WHEN status_setoran = 'menunggu_diterima' THEN 1 ELSE 0 END) as menunggu,
            SUM(CASE WHEN status_setoran = 'diterima' THEN 1 ELSE 0 END) as diterima,
            SUM(CASE WHEN status_setoran = 'ditolak' THEN 1 ELSE 0 END) as ditolak,
            SUM(CASE WHEN status_setoran = 'dibatalkan' THEN 1 ELSE 0 END) as dibatalkan
        ")->first();

        $counts = [
            'menunggu'   => (int) $countRow->menunggu,
            'diterima'   => (int) $countRow->diterima,
            'ditolak'    => (int) $countRow->ditolak,
            'dibatalkan' => (int) $countRow->dibatalkan,
        ];

        // Apply tab filter
        $query = (clone $baseQuery)
            ->with([
                'cabang', 'kas',
                'setoranPasangan' => fn($q) => $q->withTrashed()->with(['cabang', 'kas']),
                'diterimaOleh', 'createdBy',
            ])
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id');

        match($tab) {
            'menunggu'   => $query->where('status_setoran', 'menunggu_diterima'),
            'diterima'   => $query->where('status_setoran', 'diterima'),
            'ditolak'    => $query->where('status_setoran', 'ditolak'),
            'dibatalkan' => $query->where('status_setoran', 'dibatalkan'),
            default      => null,
        };

        // Search multi-field — TAMBAHAN, tidak mengganti filter tab/cabang di atas
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_transaksi', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('kas', fn ($k) => $k->where('nama_kas', 'like', "%{$search}%"))
                  ->orWhereHas('cabang', fn ($c) => $c->where('nama_cabang', 'like', "%{$search}%"));
            });
        }

        $setorans      = $query->paginate(20)->withQueryString();
        $cabangOptions = $user->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('nama_cabang')->get()
            : collect();

        return view('setoran.index', compact('setorans', 'tab', 'counts', 'cabangOptions'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('setoran.create'), 403);

        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $cabangId       = $activeCabangId ?? $user->defaultCabangId();

        $kasAsal = Kas::aktif()->where('cabang_id', $cabangId)->get();

        $cabangTujuans = Cabang::aktif()
            ->where('id', '!=', $cabangId)
            ->orderBy('nama_cabang')
            ->get();

        $cabangAsal = Cabang::find($cabangId);

        return view('setoran.create', compact('kasAsal', 'cabangTujuans', 'cabangAsal'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('setoran.create'), 403);

        $request->validate([
            'tanggal'          => 'required|date',
            'kas_asal_id'      => 'required|exists:kas,id',
            'cabang_tujuan_id' => 'required|exists:cabangs,id',
            'kas_tujuan_id'    => 'required|exists:kas,id',
            'jumlah'           => 'required|numeric|min:1000',
            'keterangan'       => 'required|string|max:255',
            'bukti'            => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'catatan'          => 'nullable|string|max:500',
        ]);

        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $cabangAsalId   = $activeCabangId ?? $user->defaultCabangId();

        $kasAsal = Kas::findOrFail($request->kas_asal_id);
        abort_unless($kasAsal->cabang_id == $cabangAsalId, 403, 'Kas bukan milik cabang aktif.');

        if ($kasAsal->saldo_sekarang < $request->jumlah) {
            return back()->withInput()
                ->with('error', 'Saldo kas "' . $kasAsal->nama_kas . '" tidak cukup. Saldo: Rp ' . number_format($kasAsal->saldo_sekarang, 0, ',', '.'));
        }

        $kasTujuan = Kas::findOrFail($request->kas_tujuan_id);
        abort_unless($kasTujuan->cabang_id == $request->cabang_tujuan_id, 403, 'Kas tujuan bukan milik cabang tujuan.');

        $buktiPath = $request->file('bukti')->store('bukti_transaksi', 'public');

        $kategoriOut = KategoriTransaksi::where('kode', 'SETOR-OUT')->first();
        $kategoriIn  = KategoriTransaksi::where('kode', 'SETOR-IN')->first();

        $prefix     = 'TRX-' . date('Ymd');
        $lastNomor  = TransaksiKeuangan::withTrashed()
            ->where('nomor_transaksi', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('nomor_transaksi');
        $seq = 1;
        if ($lastNomor && preg_match('/(\d+)$/', $lastNomor, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        $trxOut = null;
        DB::transaction(function () use (
            $request, $cabangAsalId, $kasAsal, $kasTujuan,
            $kategoriOut, $kategoriIn, $buktiPath, $prefix, &$seq, &$trxOut
        ) {
            $nomorOut = $prefix . '-' . str_pad($seq++, 4, '0', STR_PAD_LEFT);
            $nomorIn  = $prefix . '-' . str_pad($seq,   4, '0', STR_PAD_LEFT);

            // SETOR-OUT: kurangi saldo kas asal SEKARANG (uang sudah keluar dari laci)
            $trxOut = TransaksiKeuangan::create([
                'cabang_id'         => $cabangAsalId,
                'kas_id'            => $kasAsal->id,
                'nomor_transaksi'   => $nomorOut,
                'tanggal_transaksi' => $request->tanggal,
                'tipe'              => TipeTransaksiKeuangan::Pengeluaran,
                'kategori'          => KategoriEnum::Lainnya,
                'kategori_id'       => $kategoriOut?->id,
                'keterangan'        => $request->keterangan,
                'jumlah'            => $request->jumlah,
                'bukti_path'        => $buktiPath,
                'catatan'           => $request->catatan,
                'status_setoran'    => StatusSetoran::MenungguDiterima,
                'created_by'        => auth()->id(),
            ]);

            // SETOR-IN: dicatat tapi kas tujuan TIDAK bertambah sampai diterima
            $trxIn = TransaksiKeuangan::create([
                'cabang_id'         => $request->cabang_tujuan_id,
                'kas_id'            => $kasTujuan->id,
                'nomor_transaksi'   => $nomorIn,
                'tanggal_transaksi' => $request->tanggal,
                'tipe'              => TipeTransaksiKeuangan::Pemasukan,
                'kategori'          => KategoriEnum::Lainnya,
                'kategori_id'       => $kategoriIn?->id,
                'keterangan'        => 'Terima setoran: ' . $request->keterangan,
                'jumlah'            => $request->jumlah,
                'bukti_path'        => $buktiPath,
                'setoran_pair_id'   => $trxOut->id,
                'catatan'           => $request->catatan,
                'status_setoran'    => StatusSetoran::MenungguDiterima,
                'created_by'        => auth()->id(),
            ]);

            $trxOut->update(['setoran_pair_id' => $trxIn->id]);

            // Kurangi saldo kas asal (uang sudah keluar)
            $kasAsal->decrement('saldo_sekarang', (float) $request->jumlah);
            // Kas tujuan TIDAK ditambah — menunggu konfirmasi terima
        });

        // Notifikasi ke Owner/Admin Pusat: ada setoran baru menunggu konfirmasi
        if ($trxOut) {
            $trxOut->load('cabang');
            NotificationService::send(
                NotificationService::getOwnerAndAdminPusat(),
                new SetoranNotification($trxOut, 'baru')
            );
        }

        return redirect()->route('setoran.index')
            ->with('success', 'Setoran Rp ' . number_format($request->jumlah, 0, ',', '.') . ' berhasil dikirim. Menunggu konfirmasi dari penerima.');
    }

    public function show($setoran)
    {
        abort_unless(auth()->user()->can('setoran.view'), 403);

        $setoran = TransaksiKeuangan::withTrashed()->findOrFail($setoran);

        // Normalkan ke SETOR-OUT untuk konsistensi tampilan
        $setoran->loadMissing('kategoriDinamis');
        if ($setoran->kategoriDinamis?->kode === 'SETOR-IN' && $setoran->setoran_pair_id) {
            $setoran = TransaksiKeuangan::withTrashed()->findOrFail($setoran->setoran_pair_id);
            $setoran->loadMissing('kategoriDinamis');
        }

        $setoran->load(['cabang', 'kas', 'createdBy', 'diterimaOleh', 'kategoriDinamis']);

        // Load pair dengan withTrashed (agar tetap tampil untuk ditolak/dibatalkan)
        if ($setoran->setoran_pair_id) {
            $pair = TransaksiKeuangan::withTrashed()
                ->with(['cabang', 'kas'])
                ->find($setoran->setoran_pair_id);
            $setoran->setRelation('setoranPasangan', $pair);
        }

        return view('setoran.show', compact('setoran'));
    }

    /**
     * Terima setoran: tambah saldo kas tujuan, update status diterima.
     */
    public function terima(TransaksiKeuangan $setoran)
    {
        abort_unless(auth()->user()->can('setoran.terima'), 403);

        $trxOut = $this->resolveSetoranOut($setoran);
        abort_unless($trxOut, 404, 'Transaksi setoran tidak ditemukan.');
        abort_unless(
            $trxOut->status_setoran === StatusSetoran::MenungguDiterima,
            422,
            'Setoran sudah diproses sebelumnya (status: ' . ($trxOut->status_setoran?->label() ?? 'tidak diketahui') . ').'
        );

        $trxIn     = $trxOut->setoranPasangan;
        $kasTujuan = $trxIn ? Kas::find($trxIn->kas_id) : null;
        abort_unless($kasTujuan, 500, 'Kas tujuan tidak ditemukan. Hubungi administrator.');

        DB::transaction(function () use ($trxOut, $trxIn, $kasTujuan) {
            // Tambah saldo kas tujuan (baru sekarang saat diterima)
            $kasTujuan->increment('saldo_sekarang', (float) $trxOut->jumlah);

            $now    = now();
            $userId = auth()->id();

            $trxOut->update([
                'status_setoran'  => StatusSetoran::Diterima,
                'diterima_oleh_id' => $userId,
                'waktu_diterima'  => $now,
            ]);

            if ($trxIn) {
                $trxIn->update([
                    'status_setoran'  => StatusSetoran::Diterima,
                    'diterima_oleh_id' => $userId,
                    'waktu_diterima'  => $now,
                ]);
            }
        });

        // Notifikasi ke Manajer Cabang pengirim: setoran mereka sudah diterima
        $trxOut->load('cabang');
        NotificationService::send(
            NotificationService::getManajerCabang($trxOut->cabang_id),
            new SetoranNotification($trxOut, 'diterima')
        );

        $jumlahFmt = number_format($trxOut->jumlah, 0, ',', '.');
        return redirect()->route('setoran.show', $trxOut)
            ->with('success', "Setoran Rp {$jumlahFmt} berhasil diterima. Saldo {$kasTujuan->nama_kas} bertambah.");
    }

    /**
     * Tolak setoran: kembalikan saldo kas asal, simpan alasan.
     */
    public function tolak(Request $request, TransaksiKeuangan $setoran)
    {
        abort_unless(auth()->user()->can('setoran.terima'), 403);

        $request->validate([
            'alasan_tolak' => 'required|string|min:5|max:500',
        ], [
            'alasan_tolak.required' => 'Alasan penolakan wajib diisi.',
            'alasan_tolak.min'      => 'Alasan minimal 5 karakter.',
        ]);

        $trxOut = $this->resolveSetoranOut($setoran);
        abort_unless($trxOut, 404);
        abort_unless(
            $trxOut->status_setoran === StatusSetoran::MenungguDiterima,
            422,
            'Setoran sudah diproses sebelumnya.'
        );

        $trxIn   = $trxOut->setoranPasangan;
        $kasAsal = Kas::find($trxOut->kas_id);
        abort_unless($kasAsal, 500, 'Kas asal tidak ditemukan.');

        DB::transaction(function () use ($trxOut, $trxIn, $kasAsal, $request) {
            // Kembalikan saldo kas asal
            $kasAsal->increment('saldo_sekarang', (float) $trxOut->jumlah);

            $trxOut->update([
                'status_setoran'       => StatusSetoran::Ditolak,
                'alasan_tolak_setoran' => $request->alasan_tolak,
            ]);

            if ($trxIn) {
                $trxIn->update([
                    'status_setoran'       => StatusSetoran::Ditolak,
                    'alasan_tolak_setoran' => $request->alasan_tolak,
                ]);
            }

            // Soft-delete agar tidak muncul di laporan keuangan harian
            $trxOut->delete();
            if ($trxIn) $trxIn->delete();
        });

        // Notifikasi ke Manajer Cabang pengirim: setoran ditolak + alasan
        $trxOut->load('cabang');
        NotificationService::send(
            NotificationService::getManajerCabang($trxOut->cabang_id),
            new SetoranNotification($trxOut, 'ditolak')
        );

        $jumlahFmt = number_format($trxOut->jumlah, 0, ',', '.');
        return redirect()->route('setoran.show', $trxOut)
            ->with('error_info', "Setoran ditolak. Saldo {$kasAsal->nama_kas} dikembalikan Rp {$jumlahFmt}.");
    }

    /**
     * Batal oleh pengirim: kembalikan saldo asal, set dibatalkan.
     */
    public function batal(TransaksiKeuangan $setoran)
    {
        abort_unless(auth()->user()->can('setoran.batal'), 403);

        $trxOut = $this->resolveSetoranOut($setoran);
        abort_unless($trxOut, 404);
        abort_unless(
            $trxOut->status_setoran === StatusSetoran::MenungguDiterima,
            422,
            'Hanya setoran "Menunggu Diterima" yang bisa dibatalkan.'
        );

        // Pastikan pengirim dari cabang yang sama, atau Owner
        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        if (!$user->canAccessAllBranches() && $trxOut->cabang_id != $activeCabangId) {
            abort(403, 'Hanya pengirim yang dapat membatalkan setoran ini.');
        }

        $trxIn   = $trxOut->setoranPasangan;
        $kasAsal = Kas::find($trxOut->kas_id);

        DB::transaction(function () use ($trxOut, $trxIn, $kasAsal) {
            if ($kasAsal) {
                $kasAsal->increment('saldo_sekarang', (float) $trxOut->jumlah);
            }
            $trxOut->update(['status_setoran' => StatusSetoran::Dibatalkan]);
            if ($trxIn) {
                $trxIn->update(['status_setoran' => StatusSetoran::Dibatalkan]);
            }

            // Soft-delete agar tidak muncul di laporan keuangan harian
            $trxOut->delete();
            if ($trxIn) $trxIn->delete();
        });

        $jumlahFmt = number_format($trxOut->jumlah, 0, ',', '.');
        return redirect()->route('setoran.index')
            ->with('success', "Setoran dibatalkan. Saldo {$kasAsal?->nama_kas} dikembalikan Rp {$jumlahFmt}.");
    }

    /**
     * Hapus permanen oleh Owner/Admin — tangani saldo sesuai status saat ini.
     */
    public function destroy($setoran)
    {
        abort_unless(auth()->user()->can('setoran.delete'), 403);

        $setoran = TransaksiKeuangan::withTrashed()->findOrFail($setoran);

        $trxOut = $this->resolveSetoranOut($setoran);
        // Load pair dengan withTrashed agar record ditolak/dibatalkan ikut dibersihkan
        $pairId = $trxOut?->setoran_pair_id;
        $trxIn  = $pairId ? TransaksiKeuangan::withTrashed()->find($pairId) : null;

        // Guard: kalau status sudah "diterima" (kedua kas pernah terpengaruh)
        // tapi pasangannya tidak bisa ditemukan (setoran_pair_id NULL/rusak atau
        // pair sudah hilang), JANGAN diam-diam proses cuma satu sisi -- itu bikin
        // kas asal ke-kredit tapi kas tujuan tidak ikut dikoreksi (bug nyata,
        // ditemukan lewat data corrupted audit produksi 2026-07-27). Status
        // menunggu_diterima/ditolak/dibatalkan tidak butuh $trxIn sama sekali
        // (cuma kas asal yang perlu dibalik atau tidak ada yang perlu dibalik),
        // jadi guard ini spesifik ke status Diterima saja -- backward compat
        // untuk jalur lain tetap utuh.
        if ($trxOut?->status_setoran === StatusSetoran::Diterima && !$trxIn) {
            return back()->with('error',
                'Pasangan setoran tidak ditemukan (mungkin sudah dihapus atau data corrupted). '
                . 'Hubungi admin atau cek menu Data Terhapus sebelum menghapus setoran ini.');
        }

        DB::transaction(function () use ($setoran, $trxOut, $trxIn) {
            $status = $trxOut?->status_setoran;

            if ($status === null || $status === StatusSetoran::MenungguDiterima) {
                // Hanya kas asal yang sudah berkurang → kembalikan
                if ($trxOut?->kas_id) {
                    Kas::where('id', $trxOut->kas_id)->increment('saldo_sekarang', (float) $trxOut->jumlah);
                }
            } elseif ($status === StatusSetoran::Diterima) {
                // Kedua kas sudah terpengaruh → balik keduanya
                if ($trxOut?->kas_id) {
                    Kas::where('id', $trxOut->kas_id)->increment('saldo_sekarang', (float) $trxOut->jumlah);
                }
                if ($trxIn?->kas_id) {
                    Kas::where('id', $trxIn->kas_id)->decrement('saldo_sekarang', (float) $trxOut->jumlah);
                }
            }
            // status ditolak/dibatalkan: saldo sudah dikembalikan, tidak ada yang perlu dibalik

            // Hapus file bukti (shared)
            $buktiPath = $trxOut?->bukti_path ?? $setoran->bukti_path;
            if ($buktiPath) {
                Storage::disk('public')->delete($buktiPath);
            }

            // Soft-delete kedua record
            if ($trxIn) {
                $trxIn->update(['setoran_pair_id' => null]);
                $trxIn->delete();
            }
            if ($trxOut) {
                $trxOut->update(['setoran_pair_id' => null]);
                $trxOut->delete();
            } elseif (!$trxOut) {
                $setoran->delete();
            }
        });

        return redirect()->route('setoran.index')->with('success', 'Setoran berhasil dihapus permanen.');
    }

    /** AJAX: kas aktif untuk cabang tujuan */
    public function getKasTujuan(Request $request)
    {
        abort_unless(auth()->user()->can('setoran.create'), 403);

        $cabangId = $request->input('cabang_id');
        if (!$cabangId) {
            return response()->json([]);
        }

        $kass = Kas::aktif()
            ->where('cabang_id', $cabangId)
            ->get(['id', 'nama_kas', 'tipe_kas', 'saldo_sekarang']);

        return response()->json($kass);
    }

    /** AJAX: ringkasan kas hari ini untuk form setoran */
    public function getKasSummary(Kas $kas, string $tanggal)
    {
        abort_unless(auth()->user()->can('setoran.create'), 403);

        $user           = auth()->user();
        $activeCabangId = session('active_cabang_id') ?? $user->defaultCabangId();
        abort_unless($kas->cabang_id == $activeCabangId || $user->canAccessAllBranches(), 403);

        try {
            $date = \Carbon\Carbon::parse($tanggal)->format('Y-m-d');
        } catch (\Exception $e) {
            $date = today()->format('Y-m-d');
        }

        $transaksiHari = TransaksiKeuangan::with('kategoriDinamis')
            ->where('kas_id', $kas->id)
            ->whereDate('tanggal_transaksi', $date)
            ->where(function ($q) {
                $q->whereNull('status_setoran')
                  ->orWhereNotIn('status_setoran', ['dibatalkan', 'ditolak']);
            })
            ->get();

        $pemasukanItems  = $transaksiHari->filter(fn ($t) => $t->tipe === TipeTransaksiKeuangan::Pemasukan);
        $pengeluaranItems = $transaksiHari->filter(fn ($t) => $t->tipe === TipeTransaksiKeuangan::Pengeluaran);

        $pemasukanList  = [];
        $totalPemasukan = 0;
        foreach ($pemasukanItems->groupBy(fn ($t) => $t->kategoriDinamis?->nama ?? $t->kategori?->label() ?? 'Lainnya') as $label => $items) {
            $total           = (float) $items->sum('jumlah');
            $pemasukanList[] = ['label' => $label, 'total' => $total];
            $totalPemasukan += $total;
        }

        $pengeluaranList  = [];
        $totalPengeluaran = 0;
        foreach ($pengeluaranItems->groupBy(fn ($t) => $t->kategoriDinamis?->nama ?? $t->kategori?->label() ?? 'Lainnya') as $label => $items) {
            $total             = (float) $items->sum('jumlah');
            $pengeluaranList[] = ['label' => $label, 'total' => $total];
            $totalPengeluaran += $total;
        }

        $saldoSekarang  = (float) $kas->saldo_sekarang;
        $saldoAwalHari  = max(0, $saldoSekarang - $totalPemasukan + $totalPengeluaran);
        $reserved       = 300000;
        $estimasiSetor  = max(0, $saldoSekarang - $reserved);

        return response()->json([
            'kas_nama'         => $kas->nama_kas,
            'saldo_sekarang'   => $saldoSekarang,
            'saldo_awal_hari'  => $saldoAwalHari,
            'pemasukan'        => $pemasukanList,
            'total_pemasukan'  => $totalPemasukan,
            'pengeluaran'      => $pengeluaranList,
            'total_pengeluaran' => $totalPengeluaran,
            'estimasi_setor'   => $estimasiSetor,
            'reserved'         => $reserved,
        ]);
    }

    /**
     * Helper: normalkan ke record SETOR-OUT (sisi pengirim).
     * Menerima baik SETOR-OUT maupun SETOR-IN.
     */
    private function resolveSetoranOut(TransaksiKeuangan $setoran): ?TransaksiKeuangan
    {
        $setoran->loadMissing(['kategoriDinamis', 'setoranPasangan.kas', 'kas']);

        if ($setoran->kategoriDinamis?->kode === 'SETOR-OUT') {
            return $setoran;
        }

        // Ini adalah SETOR-IN, cari pasangannya (SETOR-OUT) — withTrashed untuk ditolak/dibatalkan
        if ($setoran->setoran_pair_id) {
            return TransaksiKeuangan::withTrashed()
                ->with(['setoranPasangan.kas', 'kas', 'kategoriDinamis'])
                ->find($setoran->setoran_pair_id);
        }

        return null;
    }
}
