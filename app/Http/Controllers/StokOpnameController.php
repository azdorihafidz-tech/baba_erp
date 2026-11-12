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
        $hitungButa = (bool) (PengaturanUmum::getSetting()->opname_hitung_buta ?? true);

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
        $batasFoto = (float) (PengaturanUmum::getSetting()->opname_batas_foto_hilang ?? 200000);

        return view('stok-opname.show', compact('o', 'rows', 'boleh', 'lihatSistem', 'stokKini', 'masalah', 'sebabSetujui', 'batasFoto'));
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
