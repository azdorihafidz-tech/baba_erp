<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Closing;
use App\Models\Kas;
use App\Services\ClosingArsipService;
use App\Services\ClosingService;
use App\Support\ClosingLock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Closing / Tutup Buku — preview real-time, simpan & kunci (harian,
 * bulanan, keseluruhan), riwayat, arsip, buka kunci.
 */
class ClosingController extends Controller
{
    public function __construct(
        private ClosingService $service,
        private ClosingArsipService $arsip,
    ) {
    }

    // =====================================================================
    // Preview
    // =====================================================================

    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->can('closing.view'), 403);

        [$cabangs, $cabangId, $bolehKonsolidasi] = $this->resolveCabang($request);

        $jenisDefault = $user->can('closing.bulanan') ? 'bulanan' : 'harian';
        $periode = $this->service->resolvePeriode(
            (string) $request->input('jenis', $jenisDefault),
            $request->input('tanggal'),
            $request->input('bulan')
        );

        $data = $this->service->hitung($periode['start'], $periode['end'], $cabangId);

        // Status closing untuk pilihan saat ini
        $closingAktif = null;
        $cabangBelumTutupBuku = collect();
        if ($periode['jenis'] === 'harian' && $cabangId) {
            $closingAktif = ClosingLock::closingYangMengunci($cabangId, $periode['start']);
        } elseif ($periode['jenis'] === 'bulanan') {
            $closingAktif = Closing::final()->where('jenis', 'bulanan')
                ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId), fn ($q) => $q->whereNull('cabang_id'))
                ->whereDate('periode_mulai', $periode['start']->toDateString())
                ->whereDate('periode_selesai', $periode['end']->toDateString())
                ->first();
            if (! $cabangId) {
                $cabangBelumTutupBuku = $this->arsip->cabangBelumTutupBuku($periode['start'], $periode['end']);
            }
        }

        $rekapMingguan = $periode['jenis'] === 'mingguan'
            ? $this->rekapMingguan($periode['start'], $periode['end'], $cabangId ? $cabangs->where('id', $cabangId) : $cabangs)
            : [];

        $kasTunai = $cabangId
            ? Kas::where('cabang_id', $cabangId)->where('tipe_kas', 'tunai')->where('is_active', true)->orderBy('nama_kas')->get()
            : collect();
        $saldoSistem = collect($data['saldo_kas']['rows'])->pluck('saldo_akhir', 'kas_id');

        return view('closing.index', [
            'periode'          => $periode,
            'data'             => $data,
            'cabangs'          => $cabangs,
            'cabangId'         => $cabangId,
            'bolehKonsolidasi' => $bolehKonsolidasi,
            'tglMulaiPeriode'  => ClosingService::tanggalMulaiPeriode(),
            'closingAktif'     => $closingAktif,
            'cabangBelumTutupBuku' => $cabangBelumTutupBuku,
            'rekapMingguan'    => $rekapMingguan,
            'kasTunai'         => $kasTunai,
            'saldoSistem'      => $saldoSistem,
            'periodeBerakhir'  => $periode['end']->lte(Carbon::today()),
        ]);
    }

    /**
     * Rekap mingguan: status closing harian tiap hari × cabang (tanpa kunci
     * terpisah — minggu dianggap tertutup kalau semua harinya sudah closing).
     */
    private function rekapMingguan(Carbon $start, Carbon $end, Collection $cabangs): array
    {
        $closings = Closing::final()
            ->whereIn('cabang_id', $cabangs->pluck('id'))
            ->whereDate('periode_mulai', '<=', $end->toDateString())
            ->whereDate('periode_selesai', '>=', $start->toDateString())
            ->get(['id', 'jenis', 'cabang_id', 'periode_mulai', 'periode_selesai', 'total_selisih_fisik']);

        $rows = [];
        foreach ($cabangs as $cabang) {
            $hari = [];
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $c = $closings->first(fn ($x) => $x->cabang_id == $cabang->id
                    && $x->periode_mulai->lte($d) && $x->periode_selesai->gte($d));
                $hari[] = [
                    'tanggal' => $d->toDateString(),
                    'closing_id' => $c?->id,
                    'jenis' => $c?->jenis,
                    'selisih' => $c && $c->jenis === 'harian' ? $c->total_selisih_fisik : null,
                    'akan_datang' => $d->gt(Carbon::today()),
                ];
            }
            $rows[] = ['cabang' => $cabang->nama_cabang, 'cabang_id' => $cabang->id, 'hari' => $hari];
        }

        return $rows;
    }

    // =====================================================================
    // Simpan & kunci
    // =====================================================================

    public function storeHarian(Request $request)
    {
        abort_unless(auth()->user()->can('closing.harian'), 403);

        $validated = $request->validate([
            'cabang_id' => ['required', 'integer'],
            'tanggal'   => ['required', 'date_format:Y-m-d'],
            'fisik'     => ['nullable', 'array'],
            'fisik.*'   => ['required', 'numeric', 'min:0'],
            'catatan'   => ['nullable', 'string', 'max:1000'],
        ], [
            'fisik.*.required' => 'Jumlah uang fisik wajib diisi untuk setiap kas tunai.',
            'fisik.*.min'      => 'Jumlah uang fisik tidak boleh minus.',
        ]);

        $cabangId = $this->pastikanAksesCabang((int) $validated['cabang_id']);
        $fisik = $validated['fisik'] ?? [];

        $kasTunaiIds = Kas::where('cabang_id', $cabangId)->where('tipe_kas', 'tunai')->where('is_active', true)->pluck('id');
        $kurang = $kasTunaiIds->reject(fn ($id) => array_key_exists($id, $fisik));
        if ($kurang->isNotEmpty()) {
            return back()->withInput()->with('error', 'Jumlah uang fisik wajib diisi untuk setiap kas tunai.');
        }

        try {
            $closing = $this->arsip->simpanHarian(
                $cabangId, Carbon::createFromFormat('Y-m-d', $validated['tanggal']), auth()->id(),
                array_intersect_key($fisik, $kasTunaiIds->flip()->all()), $validated['catatan'] ?? null
            );
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', e($e->getMessage()));
        }

        return redirect()->route('closing.show', $closing)
            ->with('success', 'Closing harian tersimpan dan tanggal ini sudah dikunci.');
    }

    public function storeBulanan(Request $request)
    {
        abort_unless(auth()->user()->can('closing.bulanan'), 403);

        $validated = $request->validate([
            'cabang_id' => ['required'],
            'bulan'     => ['required', 'date_format:Y-m'],
            'catatan'   => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            if ($validated['cabang_id'] === 'semua') {
                abort_unless(auth()->user()->canAccessAllBranches() && auth()->user()->can('closing.konsolidasi'), 403);
                $closing = $this->arsip->simpanKeseluruhan($validated['bulan'], auth()->id(), $validated['catatan'] ?? null);
                $pesan = 'Tutup buku keseluruhan tersimpan.';
            } else {
                $cabangId = $this->pastikanAksesCabang((int) $validated['cabang_id']);
                $closing = $this->arsip->simpanBulanan($cabangId, $validated['bulan'], auth()->id(), $validated['catatan'] ?? null);
                $pesan = 'Tutup buku bulanan tersimpan dan periode ini sudah dikunci.';
            }
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', e($e->getMessage()));
        }

        return redirect()->route('closing.show', $closing)->with('success', $pesan);
    }

    // =====================================================================
    // Riwayat & arsip
    // =====================================================================

    public function riwayat(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->can('closing.view'), 403);

        [$cabangs, , $bolehKonsolidasi] = $this->resolveCabang($request);

        $query = Closing::with('cabang:id,nama_cabang', 'ditutupOleh:id,name', 'dibukaOleh:id,name')
            ->orderByDesc('periode_mulai')->orderByDesc('id');

        // Filter cabang WAJIB manual (CLAUDE.md 3.4)
        $query->where(function ($q) use ($cabangs, $bolehKonsolidasi) {
            $q->whereIn('cabang_id', $cabangs->pluck('id'));
            if ($bolehKonsolidasi) {
                $q->orWhereNull('cabang_id');
            }
        });

        $filterCabang = $request->input('cabang_id');
        if ($filterCabang === 'semua' && $bolehKonsolidasi) {
            $query->whereNull('cabang_id');
        } elseif ($filterCabang && $cabangs->contains('id', (int) $filterCabang)) {
            $query->where('cabang_id', (int) $filterCabang);
        }
        if (in_array($request->input('jenis'), ['harian', 'bulanan'], true)) {
            $query->where('jenis', $request->input('jenis'));
        }
        if (in_array($request->input('status'), [Closing::STATUS_FINAL, Closing::STATUS_DIBUKA], true)) {
            $query->where('status', $request->input('status'));
        }

        return view('closing.riwayat', [
            'closings'         => $query->paginate(25)->withQueryString(),
            'cabangs'          => $cabangs,
            'bolehKonsolidasi' => $bolehKonsolidasi,
        ]);
    }

    public function show(Closing $closing)
    {
        abort_unless(auth()->user()->can('closing.view'), 403);
        $this->pastikanBolehLihat($closing);

        $closing->load('cabang:id,nama_cabang', 'ditutupOleh:id,name', 'dibukaOleh:id,name', 'kasRows');

        $versiLain = Closing::where('jenis', $closing->jenis)
            ->when($closing->cabang_id, fn ($q) => $q->where('cabang_id', $closing->cabang_id), fn ($q) => $q->whereNull('cabang_id'))
            ->whereDate('periode_mulai', $closing->periode_mulai->toDateString())
            ->whereDate('periode_selesai', $closing->periode_selesai->toDateString())
            ->where('id', '!=', $closing->id)
            ->orderByDesc('versi')
            ->get(['id', 'versi', 'status', 'ditutup_pada', 'laba_bersih', 'omzet']);

        return view('closing.show', compact('closing', 'versiLain'));
    }

    public function buka(Request $request, Closing $closing)
    {
        abort_unless(auth()->user()->can('closing.buka_kunci'), 403);
        $this->pastikanBolehLihat($closing);

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan membuka kunci wajib diisi.',
            'alasan.min'      => 'Alasan minimal 10 karakter, jelaskan apa yang akan dikoreksi.',
        ]);

        try {
            $this->arsip->bukaKunci($closing, auth()->id(), $validated['alasan']);
        } catch (\DomainException $e) {
            return back()->with('error', e($e->getMessage()));
        }

        return redirect()->route('closing.show', $closing)
            ->with('success', 'Kunci periode dibuka. Setelah koreksi selesai, lakukan closing ulang (akan tersimpan sebagai versi baru).');
    }

    // =====================================================================
    // Export Excel / PDF
    // =====================================================================

    /** Export preview (angka real-time) sesuai filter halaman. */
    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('closing.export'), 403);

        [, $cabangId] = $this->resolveCabang($request);
        $periode = $this->service->resolvePeriode((string) $request->input('jenis', 'bulanan'), $request->input('tanggal'), $request->input('bulan'));
        $data = $this->service->hitung($periode['start'], $periode['end'], $cabangId);

        $jenis = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'][$periode['jenis']];

        return $this->unduh($request->input('format'), $data, $cabangId, [
            'Laporan' => "Closing {$jenis} (PREVIEW, belum dikunci)",
            'Periode' => $periode['label'] . ' (' . $periode['start']->format('d/m/Y') . ' – ' . $periode['end']->format('d/m/Y') . ')',
            'Cabang'  => $data['meta']['cabang_nama'],
        ], [], 'Closing-' . $jenis . '-' . $periode['start']->format('Ymd'));
    }

    /** Export arsip closing (angka beku). */
    public function exportArsip(Request $request, Closing $closing)
    {
        abort_unless(auth()->user()->can('closing.export'), 403);
        $this->pastikanBolehLihat($closing);

        $kasFisik = $closing->kasRows()->whereNotNull('saldo_fisik')->get()
            ->mapWithKeys(fn ($k) => [$k->kas_id => ['saldo_fisik' => (float) $k->saldo_fisik, 'selisih' => (float) $k->selisih]])
            ->all();

        return $this->unduh($request->input('format'), $closing->data, $closing->cabang_id, [
            'Laporan' => $closing->jenis_label . ' (versi ' . $closing->versi . ')',
            'Periode' => $closing->label_periode . ' (' . $closing->periode_mulai->format('d/m/Y') . ' – ' . $closing->periode_selesai->format('d/m/Y') . ')',
            'Cabang'  => $closing->nama_cabang,
            'Status'  => ($closing->isFinal() ? 'Terkunci' : 'Kunci dibuka') . ', ditutup ' . $closing->ditutup_pada->format('d/m/Y H:i') . ' oleh ' . ($closing->ditutupOleh?->name ?? '-'),
        ], $kasFisik, 'Closing-' . $closing->jenis . '-' . $closing->periode_mulai->format('Ymd') . '-v' . $closing->versi);
    }

    private function unduh(?string $format, array $data, ?int $cabangId, array $info, array $kasFisik, string $namaFile)
    {
        $namaUser = auth()->user()->name;

        if ($format === 'pdf') {
            $judul = array_shift($info);

            return \Barryvdh\DomPDF\Facade\Pdf::loadView('closing.pdf', [
                'data'          => $data,
                'cabangId'      => $cabangId,
                'kasFisik'      => $kasFisik,
                'judulLaporan'  => $judul,
                'filterInfo'    => array_diff_key($info, ['Status' => 1]),
                'statusInfo'    => $info['Status'] ?? null,
                'footerDicetak' => 'Dicetak oleh: ' . $namaUser . ' pada ' . now()->locale('id')->translatedFormat('d F Y, H:i') . ' WIB',
            ])->setPaper('a4', 'portrait')->download($namaFile . '.pdf');
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\LaporanClosingExport($data, $info, $kasFisik, $namaUser),
            $namaFile . '.xlsx'
        );
    }

    // =====================================================================
    // Akses cabang
    // =====================================================================

    /**
     * Filter cabang WAJIB manual (CabangScope dormant, CLAUDE.md 3.4):
     * role tanpa akses semua cabang hanya bisa melihat cabang miliknya.
     *
     * @return array{0: Collection, 1: int|null, 2: bool}
     */
    private function resolveCabang(Request $request): array
    {
        $user = auth()->user();
        $semuaCabang = $user->canAccessAllBranches();
        $bolehKonsolidasi = $semuaCabang && $user->can('closing.konsolidasi');

        $cabangs = $this->cabangDiizinkan();

        abort_if($cabangs->isEmpty(), 403, 'Akun Anda belum terhubung ke cabang mana pun.');

        $diminta = $request->input('cabang_id');
        if ($diminta === 'semua' && $bolehKonsolidasi) {
            return [$cabangs, null, $bolehKonsolidasi];
        }
        if ($diminta && $cabangs->contains('id', (int) $diminta)) {
            return [$cabangs, (int) $diminta, $bolehKonsolidasi];
        }

        if ($bolehKonsolidasi && $diminta === null) {
            return [$cabangs, null, $bolehKonsolidasi];
        }

        $aktif = (int) (session('active_cabang_id') ?? $user->defaultCabangId());
        $cabangId = $cabangs->contains('id', $aktif) ? $aktif : (int) $cabangs->first()->id;

        return [$cabangs, $cabangId, $bolehKonsolidasi];
    }

    private function cabangDiizinkan(): Collection
    {
        $user = auth()->user();

        return $user->canAccessAllBranches()
            ? Cabang::aktif()->orderBy('id')->get(['id', 'nama_cabang', 'tipe'])
            : $user->cabangs()->where('cabangs.is_active', true)->orderBy('cabangs.id')->get(['cabangs.id', 'nama_cabang', 'tipe']);
    }

    private function pastikanAksesCabang(int $cabangId): int
    {
        abort_unless($this->cabangDiizinkan()->contains('id', $cabangId), 403, 'Anda tidak punya akses ke cabang ini.');

        return $cabangId;
    }

    private function pastikanBolehLihat(Closing $closing): void
    {
        $user = auth()->user();
        if ($closing->cabang_id === null) {
            abort_unless($user->canAccessAllBranches() && $user->can('closing.konsolidasi'), 403);

            return;
        }
        $this->pastikanAksesCabang((int) $closing->cabang_id);
    }
}
