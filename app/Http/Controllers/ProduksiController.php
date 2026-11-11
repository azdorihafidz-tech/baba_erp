<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ProduksiBatch;
use App\Models\ResepProduksi;
use App\Models\Stock;
use App\Services\HargaResepService;
use App\Services\ProduksiService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * 4.49 Tahap B1 — menu Produksi bahan setengah jadi di gudang (porting
 * erp-coffeeshop 1139fe5). Filter cabang manual (CLAUDE.md 3.4): hanya gudang
 * yang boleh diakses user; resep & barang milik gudang itu (data per cabang 4.39).
 * Perkiraan harga bahan = harga resep FIFO (HargaResepService, sama dengan
 * komposisi Produk Jual).
 */
class ProduksiController extends Controller
{
    public function __construct(private ProduksiService $service) {}

    /** Resep milik gudang-gudang ini (barang hasil milik gudang itu, atau data lama tanpa pemilik). */
    private function resepUntuk(Collection $gudangIds)
    {
        return ResepProduksi::whereHas('item', fn ($q) => $q->where(fn ($w) => $w->whereIn('cabang_id', $gudangIds)->orWhereNull('cabang_id')));
    }

    // ===== Riwayat & catat produksi =====

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('produksi.view'), 403);

        $gudang = ProduksiService::gudangUntuk();
        $gudangIds = $gudang->pluck('id');
        $filterGudang = $request->integer('cabang_id') ?: null;

        $batches = ProduksiBatch::with(['item', 'cabang', 'user'])
            ->whereIn('cabang_id', ($filterGudang && $gudangIds->contains($filterGudang)) ? [$filterGudang] : $gudangIds)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('item_id'), fn ($q) => $q->where('item_id', $request->integer('item_id')))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->date('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->date('sampai')))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('produksi.index', [
            'batches'   => $batches,
            'gudang'    => $gudang,
            'hasilList' => Item::whereIn('id', $this->resepUntuk($gudangIds)->pluck('item_id'))->orderBy('nama_item')->get(['id', 'nama_item']),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless(auth()->user()->can('produksi.create'), 403);

        $gudang = ProduksiService::gudangUntuk();
        $reseps = $this->resepUntuk($gudang->pluck('id'))->with(['item', 'items.bahan'])->where('is_active', true)->get()
            ->filter(fn ($r) => $r->item && $r->items->isNotEmpty())
            ->sortBy(fn ($r) => $r->item->nama_item)->values();

        // Stok semua bahan & hasil di gudang yang boleh diakses — untuk cek stok live di form
        $itemIds = $reseps->flatMap(fn ($r) => $r->items->pluck('item_id')->push($r->item_id))->unique();
        $stok = Stock::whereIn('lokasi_id', $gudang->pluck('id'))->whereIn('item_id', $itemIds)->get(['item_id', 'lokasi_id', 'qty'])
            ->groupBy('lokasi_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($s) => [$s->item_id => (float) $s->qty]));

        $default = ProduksiService::gudangDefault($gudang);
        $harga = app(HargaResepService::class)->untuk($reseps->flatMap(fn ($r) => $r->items->pluck('bahan')), $default?->id);

        return view('produksi.create', [
            'gudang'     => $gudang,
            'defaultId'  => $default?->id,
            'reseps'     => $reseps,
            'resepJs'    => $reseps->mapWithKeys(fn ($r) => [$r->id => [
                'hasil'     => $r->item->nama_item,
                'gudang'    => $r->item->cabang_id,
                'satuan'    => $r->item->satuan,
                'qty_hasil' => (float) $r->qty_hasil,
                'bahan'     => $r->items->map(fn ($b) => [
                    'item_id'     => $b->item_id,
                    'nama'        => $b->bahan?->nama_item,
                    'qty'         => (float) $b->qty,
                    'satuan'      => $b->satuan,
                    'satuan_stok' => $b->bahan?->satuan,
                    'harga'       => (float) ($harga[$b->item_id]['harga'] ?? 0),
                ])->values(),
            ]]),
            'stokJs'     => $stok,
            'pilihResep' => $request->integer('resep') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('produksi.create'), 403);

        $data = $request->validate([
            'cabang_id'         => ['required', 'integer', Rule::in(ProduksiService::gudangUntuk()->pluck('id')->all())],
            'resep_produksi_id' => ['required', 'integer', 'exists:resep_produksi,id'],
            'tanggal'           => ['required', 'date', 'before_or_equal:today'],
            'qty_rencana'       => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'qty_hasil'         => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'modal_manual'      => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'catatan'           => ['nullable', 'string', 'max:500'],
        ], [
            'cabang_id.in'            => 'Gudang tidak valid atau bukan gudang yang Anda kelola.',
            'tanggal.before_or_equal' => 'Tanggal produksi tidak boleh di masa depan.',
            'qty_rencana.gt'          => 'Rencana produksi harus lebih dari 0.',
            'qty_hasil.gt'            => 'Hasil nyata harus lebih dari 0.',
        ]);
        if (! $request->boolean('pakai_modal_manual')) {
            $data['modal_manual'] = null;
        }

        try {
            $batch = $this->service->produksi($data, auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('produksi.show', $batch)
            ->with('success', "Produksi {$batch->kode_batch} tersimpan — stok {$batch->item->nama_item} bertambah " . ProduksiService::angka((float) $batch->qty_hasil) . " {$batch->item->satuan}.");
    }

    public function show(ProduksiBatch $produksi)
    {
        abort_unless(auth()->user()->can('produksi.view'), 403);
        abort_unless(ProduksiService::gudangUntuk()->contains('id', $produksi->cabang_id), 404);

        $produksi->load(['item', 'cabang', 'user', 'dibatalkanOleh', 'bahan.item']);

        return view('produksi.show', ['batch' => $produksi]);
    }

    public function batalkan(Request $request, ProduksiBatch $produksi)
    {
        abort_unless(auth()->user()->can('produksi.batalkan'), 403);
        abort_unless(ProduksiService::gudangUntuk()->contains('id', $produksi->cabang_id), 404);

        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:500']], [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        try {
            $this->service->batalkan($produksi, $data['alasan'], auth()->id());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Produksi {$produksi->kode_batch} dibatalkan — stok bahan dikembalikan.");
    }

    // ===== Resep Produksi =====

    public function resepIndex()
    {
        abort_unless(auth()->user()->can('produksi.view'), 403);

        $gudang = ProduksiService::gudangUntuk();
        $reseps = $this->resepUntuk($gudang->pluck('id'))->with(['item.cabang', 'items.bahan'])->get()
            ->sortBy(fn ($r) => $r->item?->nama_item)->values();

        return view('produksi.resep.index', [
            'reseps' => $reseps,
            'gudang' => $gudang,
            'harga'  => app(HargaResepService::class)->untuk($reseps->flatMap(fn ($r) => $r->items->pluck('bahan')), ProduksiService::gudangDefault($gudang)?->id),
        ]);
    }

    public function resepCreate(Request $request)
    {
        abort_unless(auth()->user()->can('produksi.resep.kelola'), 403);

        return view('produksi.resep.form', $this->dataFormResep(null, $request->integer('cabang_id') ?: null));
    }

    public function resepStore(Request $request)
    {
        abort_unless(auth()->user()->can('produksi.resep.kelola'), 403);

        $data = $this->validasiResep($request); // di luar try: error validasi tampil per kolom
        try {
            $resep = $this->service->simpanResep($data, null, auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('produksi.resep.index')->with('success', "Resep produksi {$resep->item->nama_item} tersimpan.");
    }

    public function resepEdit(ResepProduksi $resep)
    {
        abort_unless(auth()->user()->can('produksi.resep.kelola'), 403);
        $this->pastikanResepGudangSaya($resep);

        return view('produksi.resep.form', $this->dataFormResep($resep->load('items.bahan', 'item'), $resep->item?->cabang_id));
    }

    public function resepUpdate(Request $request, ResepProduksi $resep)
    {
        abort_unless(auth()->user()->can('produksi.resep.kelola'), 403);
        $this->pastikanResepGudangSaya($resep);

        $data = $this->validasiResep($request);
        try {
            $resep = $this->service->simpanResep($data, $resep, auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('produksi.resep.index')->with('success', "Resep produksi {$resep->item->nama_item} diperbarui.");
    }

    public function resepDestroy(ResepProduksi $resep)
    {
        abort_unless(auth()->user()->can('produksi.resep.kelola'), 403);
        $this->pastikanResepGudangSaya($resep);

        $nama = $resep->item?->nama_item;
        $resep->delete(); // riwayat produksi tetap ada (resep_produksi_id → null)

        return redirect()->route('produksi.resep.index')->with('success', "Resep produksi {$nama} dihapus. Riwayat produksinya tetap tersimpan.");
    }

    private function pastikanResepGudangSaya(ResepProduksi $resep): void
    {
        $milik = $resep->item?->cabang_id;
        abort_unless($milik === null || ProduksiService::gudangUntuk()->contains('id', $milik), 404);
    }

    private function validasiResep(Request $request): array
    {
        return $request->validate([
            'cabang_id'        => ['required', 'integer', Rule::in(ProduksiService::gudangUntuk()->pluck('id')->all())],
            'item_id'          => ['required', 'integer'],
            'qty_hasil'        => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'catatan'          => ['nullable', 'string', 'max:500'],
            'is_active'        => ['nullable', 'boolean'],
            'bahan'            => ['required', 'array', 'min:1'],
            'bahan.*.item_id'  => ['nullable', 'integer'],
            'bahan.*.qty'      => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'bahan.*.satuan'   => ['nullable', 'string', 'max:20'],
        ], [
            'cabang_id.in'     => 'Gudang tidak valid atau bukan gudang yang Anda kelola.',
            'item_id.required' => 'Pilih barang hasil produksi.',
            'qty_hasil.gt'     => 'Hasil per resep harus lebih dari 0.',
            'bahan.required'   => 'Isi minimal 1 bahan.',
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    private function dataFormResep(?ResepProduksi $resep, ?int $gudangId): array
    {
        $gudang = ProduksiService::gudangUntuk();
        $pilih = $gudang->firstWhere('id', $gudangId) ?? ProduksiService::gudangDefault($gudang);
        $barang = $pilih ? ProduksiService::barangOptions($pilih->id) : collect();
        $sudahPunya = ResepProduksi::when($resep, fn ($q) => $q->where('id', '!=', $resep->id))->pluck('item_id')->all();

        return [
            'resep'        => $resep,
            'gudang'       => $gudang,
            'gudangPilih'  => $pilih,
            'hasilOptions' => $barang->reject(fn ($i) => in_array($i->id, $sudahPunya, true))->values(),
            'bahanOptions' => $barang,
            'harga'        => app(HargaResepService::class)->untuk($barang, $pilih?->id),
        ];
    }
}
