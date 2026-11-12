<?php

namespace App\Http\Controllers;

use App\Exceptions\StokOpnameException;
use App\Models\Cabang;
use App\Models\ItemCategory;
use App\Models\PengaturanUmum;
use App\Models\StokOpname;
use App\Models\StokOpnameItem;
use App\Models\StokOpnameRincian;
use App\Services\StokOpnameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * ERP BABA Tahap 7 — Stok & Gudang › Stok Opname. Semua aturan (hak, hitung buta, selisih, keuangan) di StokOpnameService;
 * controller hanya menyaring lokasi yang boleh dilihat dan menerjemahkan StokOpnameException menjadi pesan.
 */
class StokOpnameController extends Controller
{
    public const TAB = ['berjalan' => 'Berjalan', 'menunggu' => 'Menunggu persetujuan', 'selesai' => 'Selesai', 'batal' => 'Dibatalkan', 'semua' => 'Semua'];

    public function __construct(private StokOpnameService $svc)
    {
    }

    private function izinLihat(?StokOpname $o = null): void
    {
        $u = auth()->user();
        abort_unless($u->can('stok_opname.lihat'), 403);
        if ($o) {
            abort_unless($this->svc->bolehLihat($u, $o), 404);
        }
    }

    /** Lokasi yang boleh dilihat user (semua untuk akun semua-cabang). */
    private function lokasiTerlihat()
    {
        $u = auth()->user();

        return $u->canAccessAllBranches() ? Cabang::orderBy('nama_cabang')->get() : $u->cabangs()->orderBy('nama_cabang')->get();
    }

    public function index(Request $request)
    {
        $this->izinLihat();
        $u = auth()->user();
        $lokasi = $this->lokasiTerlihat();
        $pilihLokasi = (int) $request->query('lokasi') ?: null;
        if ($pilihLokasi && ! $lokasi->contains('id', $pilihLokasi)) {
            $pilihLokasi = null;
        }
        $dasar = StokOpname::whereIn('cabang_id', $pilihLokasi ? [$pilihLokasi] : $lokasi->pluck('id'));
        $menungguIds = (clone $dasar)->with('cabang')->where('status', 'diajukan')->get()->filter(fn ($o) => $this->svc->boleh($u, $o, 'setujui'))->pluck('id');
        $tab = array_key_exists($request->query('tab'), self::TAB) ? $request->query('tab') : ($menungguIds->isNotEmpty() ? 'menunggu' : 'berjalan');
        $saring = fn ($q, $t) => match ($t) {
            'berjalan' => $q->whereIn('status', ['hitung', 'diajukan']),
            'menunggu' => $q->whereIn('id', $menungguIds),
            'selesai', 'batal' => $q->where('status', $t),
            default => $q,
        };
        $jumlah = [];
        foreach (array_keys(self::TAB) as $k) {
            $jumlah[$k] = $k === 'menunggu' ? $menungguIds->count() : $saring(clone $dasar, $k)->count();
        }
        $opname = $saring(clone $dasar, $tab)->with(['cabang', 'pembuat'])
            ->withCount(['item', 'item as dihitung_count' => fn ($q) => $q->whereNotNull('qty_fisik')])
            ->orderByDesc('id')->paginate(25)->withQueryString();
        $bisaMulai = $this->svc->lokasiUntuk($u)->isNotEmpty();

        return view('stok-opname.index', compact('opname', 'tab', 'jumlah', 'lokasi', 'pilihLokasi', 'bisaMulai'));
    }

    public function mulaiForm()
    {
        $this->izinLihat();
        $lokasi = $this->svc->lokasiUntuk(auth()->user());
        abort_if($lokasi->isEmpty(), 403);
        $kategori = ItemCategory::orderBy('nama_kategori')->get(['id', 'nama_kategori']);
        $hitungButa = StokOpnameService::pengaturan()['hitung_buta'];

        return view('stok-opname.mulai', compact('lokasi', 'kategori', 'hitungButa'));
    }

    /** Barang ber-stok di lokasi (pilihan "Barang tertentu"). */
    public function barang(Request $request)
    {
        $this->izinLihat();
        $c = $this->svc->lokasiUntuk(auth()->user())->firstWhere('id', (int) $request->query('lokasi'));
        abort_unless($c, 404);
        $q = trim((string) $request->query('q'));
        $data = $this->svc->barangLokasi($c)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nama_item', 'like', "%{$q}%")->orWhere('kode_item', 'like', "%{$q}%")->orWhere('barcode', $q)))
            ->orderBy('nama_item')->limit(300)->get(['items.id', 'items.nama_item', 'items.kode_item', 'items.satuan']);

        return response()->json($data);
    }

    public function mulai(Request $request)
    {
        $this->izinLihat();
        $c = $this->svc->lokasiUntuk(auth()->user())->firstWhere('id', (int) $request->input('cabang_id'));
        if (! $c) {
            return back()->withInput()->with('error', 'Pilih lokasi yang boleh Anda opname.');
        }
        try {
            $o = $this->svc->mulai(auth()->user(), $c, [
                'cakupan' => $request->input('cakupan'), 'kategori' => (array) $request->input('kategori', []), 'barang' => (array) $request->input('barang', []),
                'hitung_buta' => $request->boolean('hitung_buta'), 'catatan' => $request->input('catatan'),
            ]);
        } catch (StokOpnameException $e) {
            return back()->withInput()->with('error', e($e->getMessage()));
        }

        return redirect()->route('stok-opname.show', $o)->with('success', e("Opname {$o->nomor} dimulai. Silakan hitung barangnya."));
    }

    public function show(StokOpname $opname)
    {
        $this->izinLihat($opname);
        $u = auth()->user();
        $o = $opname->load(['cabang', 'pembuat', 'pengaju', 'penyetuju', 'pembatal', 'log.user']);
        $rows = StokOpnameItem::with(['barang.category', 'rincian', 'penghitung'])->where('stok_opname_id', $o->id)
            ->join('items', 'items.id', '=', 'stok_opname_item.item_id')->orderBy('items.nama_item')->select('stok_opname_item.*')->get();
        $boleh = [];
        foreach (['hitung', 'periksa', 'setujui', 'batal'] as $a) {
            $boleh[$a] = $this->svc->boleh($u, $o, $a);
        }
        $lihatSistem = $this->svc->lihatSistem($u, $o);
        // barang yang belum dihitung: tampilkan stok sistem SEKARANG (angka final dicatat saat barang dihitung)
        $stokKini = $lihatSistem ? \App\Models\Stock::where('lokasi_id', $o->cabang_id)->whereIn('item_id', $rows->pluck('item_id'))->pluck('qty', 'item_id') : collect();
        $masalah = $boleh['periksa'] ? $this->svc->masalah($o) : [];
        $sebabSetujui = $o->status === 'diajukan' && ! $boleh['setujui'] && $u->can('stok_opname.setujui') ? $this->svc->sebabTidakBolehSetujui($u, $o) : null;
        $batasFoto = StokOpnameService::pengaturan()['batas_foto_hilang'];
        // Tahap 7d: ganti rugi barang hilang per rincian + boleh membebankan
        $gr = app(\App\Services\GantiRugiStokService::class);
        $gantiRugi = $o->status === 'selesai' ? \App\Models\GantiRugiStok::with('karyawan')->where('stok_opname_id', $o->id)->get()->groupBy('stok_opname_rincian_id') : collect();
        $bolehGr = $gr->boleh($u, $o);
        $karyawan = $bolehGr ? $gr->karyawanUntuk($u, (int) $o->cabang_id) : collect();

        return view('stok-opname.show', compact('o', 'rows', 'boleh', 'lihatSistem', 'stokKini', 'masalah', 'sebabSetujui', 'batasFoto', 'gantiRugi', 'bolehGr', 'karyawan'));
    }

    /** Catat hitungan (JSON): {baris, qty} = isi jumlah; {kode, tambah: 1} = scan barcode / +1. */
    public function hitung(Request $request, StokOpname $opname)
    {
        $this->izinLihat($opname);
        $u = auth()->user();
        try {
            $row = $request->filled('baris')
                ? StokOpnameItem::where('stok_opname_id', $opname->id)->find((int) $request->input('baris'))
                : $this->svc->cariBaris($opname, (string) $request->input('kode'));
            if (! $row) {
                return response()->json(['ok' => false, 'pesan' => $request->filled('kode') ? 'Barcode "' . e($request->input('kode')) . '" tidak ada di opname ini.' : 'Barang tidak ditemukan.'], 422);
            }
            $tambah = $request->boolean('tambah');
            $qty = $tambah ? (float) ($request->input('qty', 1) ?: 1) : (float) $request->input('qty');
            if (! $tambah && ! is_numeric($request->input('qty'))) {
                throw new StokOpnameException('Isi jumlah fisik dengan angka.');
            }
            $row = $this->svc->hitung($u, $opname, $row, $qty, $tambah);
        } catch (StokOpnameException $e) {
            return response()->json(['ok' => false, 'pesan' => $e->getMessage()], 422);
        }
        $lihat = $this->svc->lihatSistem($u, $opname->fresh());
        $dihitung = StokOpnameItem::where('stok_opname_id', $opname->id)->whereNotNull('qty_fisik')->count();

        return response()->json(['ok' => true, 'baris' => [
            'id' => $row->id, 'nama' => $row->barang?->nama_item, 'fisik' => $row->qty_fisik, 'fisik_teks' => $this->svc->angka((float) $row->qty_fisik),
            'hitung_ke' => $row->hitung_ke, 'sistem' => $lihat ? $row->qty_sistem : null, 'selisih' => $lihat ? $row->selisih : null,
        ], 'dihitung' => $dihitung]);
    }

    public function hitungUlang(Request $request, StokOpname $opname)
    {
        $this->izinLihat($opname);

        return $this->aksi(fn () => $this->svc->mintaHitungUlang(auth()->user(), $opname, (array) $request->input('baris', [])), fn ($n) => "{$n} barang ditandai untuk dihitung ulang.", 'selisih');
    }

    public function rincian(Request $request, StokOpname $opname, int $baris)
    {
        $this->izinLihat($opname);
        $row = StokOpnameItem::where('stok_opname_id', $opname->id)->findOrFail($baris);
        $isi = [];
        foreach ((array) $request->input('r', []) as $i => $r) {
            $file = $request->file("r.{$i}.foto");
            $isi[] = ['penyebab' => $r['penyebab'] ?? '', 'qty' => $r['qty'] ?? 0, 'catatan' => $r['catatan'] ?? null, 'foto_dari' => $r['foto_dari'] ?? null,
                'foto' => $file && $file->isValid() ? file_get_contents($file->getRealPath()) : null];
        }

        return $this->aksi(fn () => $this->svc->simpanRincian(auth()->user(), $opname, $row, $isi), fn () => 'Penyebab ' . ($row->barang?->nama_item ?? 'barang') . ' tersimpan.', 'selisih');
    }

    public function ajukan(StokOpname $opname)
    {
        $this->izinLihat($opname);

        return $this->aksi(fn () => $this->svc->ajukan(auth()->user(), $opname), fn () => 'Opname diajukan. Menunggu persetujuan.');
    }

    public function setujui(StokOpname $opname)
    {
        $this->izinLihat($opname);

        return $this->aksi(fn () => $this->svc->setujui(auth()->user(), $opname), fn () => 'Opname disetujui. Stok dan keuangan sudah disesuaikan.');
    }

    public function tolak(Request $request, StokOpname $opname)
    {
        $this->izinLihat($opname);

        return $this->aksi(fn () => $this->svc->tolak(auth()->user(), $opname, (string) $request->input('alasan')), fn () => 'Opname ditolak dan dikembalikan ke tahap hitung.');
    }

    public function batal(Request $request, StokOpname $opname)
    {
        $this->izinLihat($opname);

        return $this->aksi(fn () => $this->svc->batal(auth()->user(), $opname, (string) $request->input('alasan')), fn () => 'Opname dibatalkan. Stok tidak berubah.');
    }

    // ───────────── Tahap 7b: Berita Acara & Laporan Kerugian Stok ─────────────

    /** Berita acara memuat stok sistem → tidak untuk penghitung buta selama opname berjalan. */
    private function izinBeritaAcara(StokOpname $o): void
    {
        $this->izinLihat($o);
        abort_unless($this->svc->lihatSistem(auth()->user(), $o), 403);
    }

    public function beritaAcara(StokOpname $opname)
    {
        $this->izinBeritaAcara($opname);
        $ba = $this->svc->beritaAcara($opname);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('laporan.pdf.stok-opname', [
            'ba' => $ba, 'judulLaporan' => 'Berita Acara Stok Opname',
            'filterInfo' => ['Nomor' => $opname->nomor, 'Lokasi' => $opname->cabang?->nama_cabang, 'Status' => $opname->status_label],
            'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
        ])->setPaper('a4', 'landscape');

        return $pdf->download("berita-acara-{$opname->nomor}.pdf");
    }

    public function beritaAcaraExcel(StokOpname $opname)
    {
        $this->izinBeritaAcara($opname);

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\StokOpnameExport($this->svc->beritaAcara($opname), auth()->user()->name), "berita-acara-{$opname->nomor}.xlsx");
    }

    public const TAB_LAPORAN = ['ringkasan' => 'Ringkasan', 'barang' => 'Per barang', 'riwayat' => 'Riwayat opname', 'rusak' => 'Stok rusak', 'manual' => 'Penyesuaian manual'];

    /**
     * Laporan Kerugian Stok. Lokasi: kosong = semua lokasi milik BABA yang boleh dilihat (tanpa toko mitra),
     * angka = satu lokasi, "semua" = termasuk toko mitra.
     */
    public function laporan(Request $request, \App\Services\LaporanKerugianStokService $lap)
    {
        abort_unless(auth()->user()->can('stok_opname.laporan'), 403);
        $terlihat = $this->lokasiTerlihat();
        $pilih = (string) $request->query('lokasi', '');
        if (ctype_digit($pilih) && $terlihat->contains('id', (int) $pilih)) {
            $ids = [(int) $pilih];
        } elseif ($pilih === 'semua') {
            $ids = $terlihat->pluck('id')->all();
        } else {
            $pilih = '';
            $baba = $terlihat->filter(fn ($c) => ! StokOpnameService::lokasiMitra($c));
            $ids = ($baba->isNotEmpty() ? $baba : $terlihat)->pluck('id')->all(); // pemilik toko mitra: tokonya sendiri
        }
        try {
            $dari = \Illuminate\Support\Carbon::parse($request->query('dari', now()->startOfMonth()->toDateString()));
            $sampai = \Illuminate\Support\Carbon::parse($request->query('sampai', now()->toDateString()));
        } catch (\Throwable $e) {
            $dari = now()->startOfMonth();
            $sampai = now();
        }
        if ($dari->gt($sampai) || $dari->diffInDays($sampai) > 370) {
            $dari = now()->startOfMonth();
            $sampai = now();
        }
        $tab = array_key_exists($request->query('tab'), self::TAB_LAPORAN) ? $request->query('tab') : 'ringkasan';
        $data = $lap->laporan($dari, $sampai, $ids);

        if ($request->boolean('excel')) {
            [$kolom, $baris] = $this->tabelLaporan($tab, $data);

            return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\LaporanOnlineExport('Kerugian Stok ' . self::TAB_LAPORAN[$tab], $kolom, $baris, auth()->user()->name),
                'kerugian-stok-' . $tab . '-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xlsx');
        }

        return view('stok-opname.laporan', ['data' => $data, 'tab' => $tab, 'dari' => $dari, 'sampai' => $sampai, 'terlihat' => $terlihat, 'pilih' => $pilih]);
    }

    /** @return array{0: array<int,string>, 1: array<int,array>} judul kolom & baris Excel per tab */
    private function tabelLaporan(string $tab, array $d): array
    {
        return match ($tab) {
            'barang' => [['Kode', 'Barang', 'Lokasi', 'Satuan', 'Jumlah kurang', 'Nilai kurang (Rp)', 'Kali opname', 'Penyebab'],
                array_map(fn ($b) => [$b['kode'], $b['nama'], $b['lokasi'], $b['satuan'], $b['qty'], $b['nilai'], $b['kali'], $b['penyebab']], $d['per_barang'])],
            'riwayat' => [['Nomor', 'Lokasi', 'Status', 'Dimulai', 'Disetujui', 'Barang', 'Akurasi (%)', 'Nilai kurang (Rp)', 'Nilai lebih (Rp)'],
                $d['riwayat']->map(fn ($o) => [$o->nomor, $o->cabang?->nama_cabang, $o->status_label, $o->created_at?->format('d/m/Y H:i'), $o->disetujui_at?->format('d/m/Y H:i'),
                    $o->item_count, $o->ringkasan['akurasi'] ?? null, $o->ringkasan['nilai_kurang'] ?? null, $o->ringkasan['nilai_lebih'] ?? null])->all()],
            'manual' => [['Lokasi', 'Pos', 'Jumlah transaksi', 'Nilai (Rp)'], array_map(fn ($m) => [$m['lokasi'], $m['nama'], $m['jumlah'], $m['nilai']], $d['manual'])],
            'rusak' => [['Barang', 'Lokasi', 'Sisa ditahan', 'Satuan', 'Nilai pokok (Rp)', 'Ditahan (hari)', 'Asal opname'],
                array_merge(array_map(fn ($r) => [$r['nama'], $r['lokasi'], $r['qty'], $r['satuan'], $r['nilai'], $r['umur'], $r['opname']], $d['rusak']['ditahan']),
                    [[], ['Tindak lanjut dalam periode', 'Kali', 'Nilai pokok (Rp)', 'Dipulihkan (Rp)']],
                    array_values(array_map(fn ($j) => [$j['label'], $j['kali'], $j['nilai_pokok'], $j['nilai_pulih']], $d['rusak']['per_jenis'])))],
            default => [['Penyebab', 'Pos keuangan', 'Barang', 'Nilai (Rp)'],
                array_merge(array_values(array_map(fn ($p) => [$p['label'], $p['akun'], $p['barang'], $p['nilai']], $d['per_penyebab'])),
                    [[], ['Lokasi', 'Opname', 'Akurasi rata-rata (%)', 'Nilai kurang (Rp)', 'Nilai lebih (Rp)', 'Selisih bersih (Rp)']],
                    array_map(fn ($l) => [$l['lokasi'], $l['opname'], $l['akurasi'], $l['nilai_kurang'], $l['nilai_lebih'], $l['bersih']], $d['per_lokasi']))],
        };
    }

    public function foto(StokOpname $opname, int $rincian)
    {
        $this->izinLihat($opname);
        $r = StokOpnameRincian::whereKey($rincian)->whereHas('itemOpname', fn ($q) => $q->where('stok_opname_id', $opname->id))->firstOrFail();
        abort_unless($r->foto && Storage::disk('local')->exists($r->foto), 404);

        return Storage::disk('local')->response($r->foto, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function aksi(callable $f, callable $pesan, ?string $tab = null)
    {
        try {
            $hasil = $f();
        } catch (StokOpnameException $e) {
            return back()->with('error', e($e->getMessage()))->withInput();
        }
        $url = url()->previous();
        if ($tab) { // kembali ke tab yang sama (buang tab lama dari query)
            [$dasar, $query] = array_pad(explode('?', $url, 2), 2, '');
            parse_str($query, $q);
            $q['tab'] = $tab;
            $url = $dasar . '?' . http_build_query($q);
        }

        return redirect($url)->with('success', e($pesan($hasil)));
    }
}
