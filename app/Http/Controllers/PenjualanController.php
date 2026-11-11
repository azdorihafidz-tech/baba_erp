<?php

namespace App\Http\Controllers;

use App\Enums\StatusOrder;
use App\Enums\StatusPurchaseOrder;
use App\Enums\TipeOrder;
use App\Enums\TipePembayaran;
use App\Enums\TipeTransaksi;
use App\Http\Requests\OrderRequest;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\JenisOlahan;
use App\Models\Kas;
use App\Models\LoyaltyProgram;
use App\Models\Order;
use App\Models\Pelanggan;
use App\Models\PurchaseOrder;
use App\Models\ResepBumbu;
use App\Models\Stock;
use App\Notifications\PenjualanNotification;
use App\Services\CascadeDeleteService;
use App\Services\NotificationService;
use App\Services\PenjualanService;
use Illuminate\Http\Request;

class PenjualanController extends Controller
{
    public function __construct(private PenjualanService $penjualanService) {}

    /**
     * FASE 2 D'mentai (2026-09-13) — POS Berkah Mulyo asli + modifikasi
     * ringan: grid produk (ganti alur pilih-dari-dropdown), tab tipe
     * transaksi, item tambahan, varian. Jasa Giling & resep-bumbu-manual
     * SENGAJA dihapus (keputusan Owner) — D'mentai tidak ada bisnis jasa
     * giling, resep sekarang otomatis per-item via `resep_bumbu.item_id`.
     */
    public function pos()
    {
        $authUser = auth()->user();
        $cabangId = session('active_cabang_id') ?? $authUser->defaultCabangId();
        $cabangAktif = $cabangId ? Cabang::find($cabangId) : null;

        // Mode "Semua Cabang" tanpa cabang default: penjualan wajib tercatat di
        // 1 cabang → minta pilih dulu, jangan ditebak (dulu error 500).
        // Gudang Pusat ikut jadi pilihan — POS gudang melayani PO cabang (4.38 Tahap C).
        if (! $cabangAktif) {
            $pilihanCabang = ($authUser->canAccessAllBranches() ? Cabang::query() : $authUser->cabangs())
                ->where('cabangs.is_active', true)
                ->orderBy('cabangs.nama_cabang')
                ->get();

            return view('penjualan.pilih-cabang', compact('pilihanCabang'));
        }

        // Tahap 2.5 D'mentai — 'produk_jadi' (tunggal) pecah jadi 2 tipe:
        // 'produk_jual' (grid utama) + 'produk_tambahan' (add-on berbayar,
        // ikut tampil di grid utama juga sesuai spesifikasi POS 7.1 poin 6).
        // Kategori utk filter grid — cuma yang benar-benar punya produk aktif.
        // "Item Tambahan" (kode TMB) ditampilkan terpisah di bawah.
        $categories = ItemCategory::where('kode_kategori', '!=', 'TMB')
            ->whereHas('items', fn ($q) => $q->whereIn('tipe', ['produk_jual', 'produk_tambahan'])->where('is_active', true)->milikCabang($cabangId))
            ->orderBy('nama_kategori')
            ->get();

        // Grid produk utama
        // 4.39 — hanya produk milik cabang POS ini
        $produkJadi = Item::whereHas('category', fn ($q) => $q->where('kode_kategori', '!=', 'TMB'))
            ->milikCabang($cabangId)
            ->whereIn('tipe', ['produk_jual', 'produk_tambahan'])
            ->where('is_active', true)
            ->with(['category', 'variants.attributeValues.attribute', 'itemCabang'])
            ->orderBy('nama_item')
            ->get()
            ->filter(fn ($item) => $item->tersediaDiCabang($cabangId))
            ->values();

        // Item Tambahan (kategori TMB: garpu gratis + saus extra berbayar).
        // Kategori TMB dikecualikan dari grid utama di atas (kode_kategori
        // != 'TMB'), jadi kedua tipe ini HARUS tetap tampil di sini supaya
        // tidak hilang dari POS — bukan dobel dengan grid utama.
        $itemTambahan = Item::whereHas('category', fn ($q) => $q->where('kode_kategori', 'TMB'))
            ->milikCabang($cabangId)
            ->whereIn('tipe', ['tambahan_gratis', 'produk_tambahan'])
            ->where('is_active', true)
            ->with('itemCabang')
            ->orderBy('nama_item')
            ->get()
            ->filter(fn ($item) => $item->tersediaDiCabang($cabangId))
            ->values();

        // 4.40 — stok tersedia & Stok Habis dari bahan resep (rumus sama dgn saat Bayar)
        $sedia = app(\App\Services\KetersediaanProdukService::class)->untuk($produkJadi->concat($itemTambahan), (int) $cabangId);
        $pasangStok = function ($item) use ($sedia) {
            $s = $sedia[$item->id];
            $item->bisa_dijual  = $s['bisa_dijual'];
            $item->stok_cabang  = (float) ($s['jumlah'] ?? 0);
            $item->stok_info    = $s['info'];
            $item->stok_singkat = $s['jumlah'] !== null ? fmt_qty($s['jumlah']) . ' ' . $s['satuan'] : '';
            $item->stok_menipis = $s['menipis'];
            return $item;
        };
        $itemTambahan = $itemTambahan->map($pasangStok);

        $produkJadi = $produkJadi
            ->map(function ($item) use ($cabangId, $pasangStok) {
                $pasangStok($item);
                $item->harga_jual  = $item->hargaEfektifDiCabang($cabangId);
                // Harga varian ikut harga outlet kalau varian tanpa harga khusus (CLAUDE.md 4.38)
                foreach ($item->variants as $v) {
                    $v->setRelation('item', $item);
                    $v->setAttribute('harga_cabang', $v->hargaEfektifDiCabang($cabangId));
                }
                return $item;
            })
            ->values();

        $pelanggans = Pelanggan::aktif()->orderBy('nama_pelanggan')->get();

        $kasList = Kas::aktif()
            ->where('cabang_id', $cabangId)
            ->get(['id', 'nama_kas', 'default_untuk']);

        $tipeTransaksiAktif = collect(TipeTransaksi::cases())
            ->filter(fn ($t) => $cabangAktif?->tipeTransaksiAktif($t))
            ->values();
        // 4.41 — tipe otomatis (pilihan disembunyikan / hanya 1 tipe aktif)
        $tipeOtomatis = $cabangAktif?->tipeTransaksiOtomatis();
        $tampilPilihanTipe = ($cabangAktif?->pilih_tipe_transaksi ?? true) !== false;

        // Bill tersimpan (Save Bill) yang belum dibayar — utk kasir lanjut proses
        $billTersimpan = Order::where('cabang_id', $cabangId)
            ->where('status', StatusOrder::Pending)
            ->orderByDesc('created_at')
            ->get();

        // Tahap C 4.38 — PO cabang ke supplier internal lokasi ini (mis. Gudang
        // Pusat) yang belum diproses: tampil di POS supaya keranjang bisa
        // diisi otomatis. PO yang sudah punya nota aktif disembunyikan.
        $poMasuk = PurchaseOrder::with(['items.item:id,nama_item,satuan,kode_item,cabang_id,tipe', 'cabang:id,nama_cabang', 'supplier:id,nama_supplier,cabang_id'])
            ->whereHas('supplier', fn ($q) => $q->where('cabang_id', $cabangId))
            ->whereIn('status', [StatusPurchaseOrder::Disetujui->value, StatusPurchaseOrder::DikirimSupplier->value])
            ->whereDoesntHave('ordersGudang', fn ($q) => $q->where('status', '!=', StatusOrder::Dibatalkan->value))
            ->orderBy('tanggal_po')
            ->get();

        // 4.49 Tahap B2 — Kirim ke Cabang: hanya di POS Gudang Pusat yang saklarnya dinyalakan
        // (Cabang → Edit → Config POS, bawaan mati), tujuan = cabang penjualan aktif lain
        $outletList = ($cabangAktif->tipe?->value === 'gudang_pusat' && $cabangAktif->kirim_cabang_aktif)
            ? Cabang::where('tipe', 'cabang')->where('is_active', true)->where('id', '!=', $cabangId)->orderBy('nama_cabang')->get()
            : collect();

        return view('penjualan.pos', compact(
            'produkJadi', 'itemTambahan', 'categories', 'pelanggans', 'cabangId',
            'cabangAktif', 'kasList', 'tipeTransaksiAktif', 'billTersimpan', 'poMasuk', 'tipeOtomatis', 'tampilPilihanTipe', 'outletList'
        ));
    }

    /**
     * AJAX helper POS Tahap 3 — ambil atribut+varian 1 item (utk modal
     * pilih varian saat kasir klik produk ber-`punya_varian=true`).
     */
    public function itemVarian(Item $item)
    {
        abort_unless($item->punya_varian, 404);

        $item->load('attributes.values', 'variants.attributeValues', 'itemCabang');
        $cabangId = (int) (session('active_cabang_id') ?? auth()->user()->defaultCabangId()) ?: null;

        return response()->json([
            'item_id'    => $item->id,
            'nama_item'  => $item->nama_item,
            'attributes' => $item->attributes->map(fn ($a) => [
                'id'     => $a->id,
                'nama'   => $a->nama,
                'values' => $a->values->map(fn ($v) => ['id' => $v->id, 'nilai' => $v->nilai]),
            ]),
            'variants' => $item->variants->where('is_active', true)->values()->map(fn ($v) => [
                'id'                 => $v->id,
                'harga_efektif'      => $v->setRelation('item', $item)->hargaEfektifDiCabang($cabangId),
                'label'              => $v->label,
                'attribute_value_ids'=> $v->attributeValues->pluck('id'),
            ]),
        ]);
    }

    /**
     * AJAX helper POS: ambil daftar bahan resep bumbu (di-scale sesuai berat
     * gilingan) untuk auto-isi form. Murni READ, tidak menyentuh order/stok/
     * kas sama sekali — hasilnya cuma dipakai JS utk mengisi field form yang
     * sudah ada persis seperti kasir isi manual.
     */
    public function resepBumbuItems(Request $request, ResepBumbu $resepBumbu)
    {
        // Tahap 4 D'mentai — param & pesan error di-generic-kan dari "berat
        // gilingan (kg)" jadi "qty (jumlah unit/porsi)", konsisten dengan
        // rename qty_per_kg -> qty_per_unit. Nama parameter query TETAP
        // "berat" (bukan diganti "qty") supaya tidak breaking untuk
        // pemanggil lama manapun yang masih mengirim query string ini —
        // maknanya saja yang berubah (bukan lagi kg, tapi jumlah unit).
        $qty = (float) $request->input('berat', 0);
        if ($qty <= 0) {
            return response()->json(['message' => 'Jumlah wajib diisi dan lebih dari 0.'], 422);
        }

        $jenisOlahanSlug = $resepBumbu->jenisOlahan?->slug;

        $items = $resepBumbu->items()->with('item')->orderBy('urutan')->get()
            ->filter(fn ($ri) => $ri->item !== null)
            ->map(function ($ri) use ($qty, $jenisOlahanSlug) {
                // qty_per_unit_dalam_kg: accessor di model ResepBumbuItem — satu-satunya
                // sumber konversi satuan (g/gram/ons/ml -> kg), dipakai juga oleh
                // preview harga di halaman Master Resep supaya konsisten.
                return [
                    'item_id'           => $ri->item_id,
                    'nama'              => $ri->item->nama_item,
                    'satuan'            => $ri->item->satuan ?? 'kg',
                    'qty'               => round($ri->qty_per_unit_dalam_kg * $qty, 3),
                    'harga_satuan'      => $ri->mode_harga === 'pakai_master' ? (float) ($ri->item->harga_jual ?? 0) : 0,
                    'is_wajib'          => (bool) $ri->is_wajib,
                    'jenis_olahan_slug' => $jenisOlahanSlug,
                ];
            })
            ->values();

        return response()->json([
            'resep_nama' => $resepBumbu->nama,
            'items'      => $items,
        ]);
    }

    /**
     * Mode "Semua Cabang": penjualan wajib tercatat di 1 cabang. Jaga-jaga
     * kalau tab POS lama masih terbuka saat cabang aktif diganti.
     */
    private function tolakTanpaCabang($cabangId)
    {
        if ($cabangId) {
            return null;
        }
        $pesan = 'Pilih cabang dulu (cabang aktif masih Semua Cabang), lalu ulangi transaksi.';

        return request()->expectsJson()
            ? response()->json(['success' => false, 'message' => $pesan], 422)
            : back()->with('error', $pesan);
    }

    public function store(OrderRequest $request)
    {
        $authUser = auth()->user();
        $cabangId = session('active_cabang_id') ?? $authUser->defaultCabangId();
        if ($tolak = $this->tolakTanpaCabang($cabangId)) {
            return $tolak;
        }

        $data = $request->validated();

        // Validasi jumlah bayar (termasuk fee/service charge) sekarang jadi
        // tanggung jawab PenjualanService (total_bayar final baru diketahui
        // di sana, setelah service charge/takeaway fee dihitung) — lihat
        // catch block di bawah.

        // Upload bukti pembayaran jika ada
        if ($request->hasFile('bukti_pembayaran')) {
            $data['bukti_pembayaran'] = $request->file('bukti_pembayaran')
                ->store('bukti-pembayaran', 'public');
        }

        try {
            $order = $this->penjualanService->buatOrder($data, (int) $cabangId);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Notifikasi transaksi besar (threshold Rp 1.000.000)
        $threshold = 1000000;
        if ((float) $order->total_bayar >= $threshold) {
            $recipients = NotificationService::getManagement((int) $cabangId);
            NotificationService::send($recipients, new PenjualanNotification($order, 'transaksi_besar'));
        }

        $copies    = (int) $request->input('print_copies', 1);
        $copies    = in_array($copies, [1, 2]) ? $copies : 1;
        $autoPrint = $request->boolean('auto_print_struk');
        // Preferensi tampilan struk (murni cetak, tidak disimpan ke order) —
        // cuma relevan kalau cabang mengizinkan sembunyi harga per item, lihat
        // checkbox conditional di pos.blade.php. Di-echo balik supaya JS bisa
        // teruskan ke openStrukModal() -> query string struk-modal.
        $tampilHargaStruk = $request->boolean('tampil_harga_struk');
        $pesan     = $order->status === StatusOrder::Proses // 4.49 — Kirim ke Cabang
            ? 'Kiriman ' . $order->nomor_order . ' tercatat — menunggu diterima ' . ($order->outletTujuan?->nama_cabang ?? 'cabang tujuan') . '.'
            : 'Order berhasil diproses! No: ' . $order->nomor_order;

        // Ajax (POS in-place) — tidak redirect supaya user gesture tetap
        // hidup untuk auto-print Bluetooth di halaman yang sama.
        if ($request->expectsJson()) {
            return response()->json([
                'success'            => true,
                'order_id'           => $order->id,
                'copies'             => $copies,
                'auto_print'         => $autoPrint,
                'tampil_harga_struk' => $tampilHargaStruk,
                'redirect_url'       => route('penjualan.show', $order),
                'message'            => $pesan,
            ]);
        }

        // Non-Ajax (backward compat): flow lama, redirect + session flash
        if ($autoPrint) {
            session()->flash('auto_print_struk_order_id', $order->id);
            session()->flash('auto_print_struk_copies', $copies);
        }

        return redirect()->route('penjualan.show', $order)->with('success', $pesan);
    }

    /**
     * Tahap 3 D'mentai — Save Bill: simpan keranjang sebagai bill (status
     * Pending), TIDAK potong stok/kas. Validasi item/tipe_transaksi sama
     * persis seperti store() TAPI payments tidak wajib (bill belum dibayar).
     */
    public function simpanBill(Request $request)
    {
        $authUser = auth()->user();
        $cabangId = session('active_cabang_id') ?? $authUser->defaultCabangId();
        if ($tolak = $this->tolakTanpaCabang($cabangId)) {
            return $tolak;
        }
        // 4.49 — Kirim ke Cabang langsung lewat tombol Kirim (bill tidak menyimpan cabang tujuan)
        if ($request->input('customer_type') === 'internal') {
            return response()->json(['success' => false, 'message' => 'Kiriman ke cabang tidak bisa disimpan sebagai bill. Gunakan tombol Kirim ke Cabang.'], 422);
        }
        // 4.41 — pilihan tipe disembunyikan / hanya 1 tipe aktif → pakai tipe otomatis cabang
        if (! $request->filled('tipe_transaksi') && ($tipeOtomatis = Cabang::find($cabangId)?->tipeTransaksiOtomatis())) {
            $request->merge(['tipe_transaksi' => $tipeOtomatis]);
        }

        $data = $request->validate([
            'pelanggan_id'       => 'nullable|exists:pelanggans,id',
            'nama_pelanggan'     => 'nullable|string|max:255',
            'telepon_pelanggan'  => 'nullable|string|max:20',
            'tipe_transaksi'     => 'required|in:dine_in,takeaway,frozen',
            'nomor_meja'         => 'nullable|string|max:20',
            'tanggal_expired_frozen' => 'nullable|date',
            'diskon'             => 'nullable|numeric|min:0',
            'catatan'            => 'nullable|string',
            'tampil_di_antrian'  => 'nullable|boolean',
            'purchase_order_id'  => 'nullable|exists:purchase_orders,id', // Tahap C 4.38
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'nullable|exists:items,id',
            'items.*.item_variant_id' => 'nullable|exists:item_variants,id',
            'items.*.nama_item'  => 'required|string',
            'items.*.qty'        => 'required|numeric|min:0.001',
            'items.*.satuan'     => 'nullable|string|max:20',
            'items.*.harga_satuan' => 'required|numeric|min:0',
            'items.*.catatan'    => 'nullable|string|max:255', // 4.44 Poin 2 — Label Produk
        ]);

        try {
            $order = $this->penjualanService->simpanBill($data, (int) $cabangId);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success'  => true,
            'order_id' => $order->id,
            'nomor_order' => $order->nomor_order,
            'message'  => "Bill {$order->nomor_order} tersimpan, belum dibayar.",
        ]);
    }

    /**
     * Tahap 3 D'mentai — finalisasi bill tersimpan: cek+potong stok, proses
     * split payment, catat kas, set status Selesai.
     */
    public function chargeBill(Request $request, Order $order)
    {
        $data = $request->validate([
            'payments'          => 'required|array|min:1',
            'payments.*.metode' => 'required|in:tunai,transfer,qris',
            'payments.*.jumlah' => 'required|numeric|min:0.01',
            'bukti_pembayaran'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        unset($data['bukti_pembayaran']);

        if ($request->hasFile('bukti_pembayaran')) {
            $order->update(['bukti_pembayaran' => $request->file('bukti_pembayaran')->store('bukti-pembayaran', 'public')]);
        }

        try {
            $order = $this->penjualanService->chargeBill($order, $data);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        $pesan = 'Bill berhasil dibayar! No: ' . $order->nomor_order;

        if ($request->expectsJson()) {
            return response()->json([
                'success'      => true,
                'order_id'     => $order->id,
                'redirect_url' => route('penjualan.show', $order),
                'message'      => $pesan,
            ]);
        }

        return redirect()->route('penjualan.show', $order)->with('success', $pesan);
    }

    /**
     * Tahap 7 D'mentai (Bug 2b) — batalkan BILL TERSIMPAN (status Pending,
     * belum dibayar). SENGAJA method+permission TERPISAH dari batalkan()
     * (yang utk order Selesai, perlu alasan kategori + reversal stok/kas
     * kompleks). Bill Pending belum pernah potong stok/catat kas sama
     * sekali (lihat PenjualanService::simpanBillInternal — cuma VALIDASI
     * stok, tidak ada reservasi), jadi cukup reuse PenjualanService::batalkan()
     * yang skip reversal kalau status bukan Selesai, tanpa perlu form
     * alasan yang berat utk aksi seringan ini.
     */
    public function batalkanBill(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('order.bill_tersimpan.batalkan'), 403, 'Anda tidak memiliki izin untuk membatalkan bill tersimpan.');

        if ($order->status !== StatusOrder::Pending) {
            $message = 'Bill ini sudah bukan berstatus Pending (mungkin sudah dibayar/dibatalkan) — tidak bisa dibatalkan lewat aksi ini.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        try {
            $this->penjualanService->batalkan($order);

            activity('Order')
                ->performedOn($order)
                ->causedBy(auth()->user())
                ->log("Bill tersimpan {$order->nomor_order} dibatalkan oleh {$request->user()->name} (belum pernah dibayar/potong stok).");
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $pesan = "Bill {$order->nomor_order} berhasil dibatalkan.";

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $pesan])
            : back()->with('success', $pesan);
    }

    /**
     * Tahap 3 D'mentai — Print Bill: preview struk SEBELUM dibayar, tidak
     * membuat/mengubah record apapun. Menerima data keranjang mentah lewat
     * POST (belum tersimpan), render partial struk yang sama dengan struk asli.
     */
    public function printBillPreview(Request $request)
    {
        $data = $request->validate([
            'nama_pelanggan'  => 'nullable|string',
            'tipe_transaksi'  => 'nullable|string',
            'nomor_meja'      => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.nama_item'    => 'required|string',
            'items.*.qty'          => 'required|numeric',
            'items.*.satuan'       => 'nullable|string',
            'items.*.harga_satuan' => 'required|numeric',
            'diskon'          => 'nullable|numeric',
        ]);

        $cabangAktif = Cabang::find(session('active_cabang_id') ?? auth()->user()->defaultCabangId());

        return view('penjualan._preview_bill', compact('data', 'cabangAktif'));
    }

    public function index(Request $request)
    {
        $authUser = auth()->user();
        $query    = Order::with(['cabang', 'kasir']);

        // Filter per cabang
        if (!$authUser->canAccessAllBranches()) {
            $cabangId = session('active_cabang_id') ?? $authUser->defaultCabangId();
            $query->where('cabang_id', $cabangId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tipe_order')) {
            $query->where('tipe_order', $request->tipe_order);
        }

        if ($request->filled('dari')) {
            $query->whereDate('tanggal_order', '>=', $request->dari);
        }

        if ($request->filled('sampai')) {
            $query->whereDate('tanggal_order', '<=', $request->sampai);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_order', 'like', "%{$search}%")
                  ->orWhere('nama_pelanggan', 'like', "%{$search}%")
                  ->orWhere('telepon_pelanggan', 'like', "%{$search}%")
                  ->orWhere('catatan', 'like', "%{$search}%");
            });
        }

        $orders  = $query->orderByDesc('tanggal_order')->orderByDesc('id')->paginate(20)->withQueryString();
        $statuses = StatusOrder::cases();
        $tipes    = TipeOrder::cases();

        return view('penjualan.index', compact('orders', 'statuses', 'tipes'));
    }

    public function show(Order $order)
    {
        $this->pastikanBolehLihat($order);
        $order->load(['cabang', 'kasir', 'pelanggan', 'items.item', 'dikerjakanOleh', 'parentOrder', 'childOrders']);

        $isOwner = auth()->user()->role === \App\Enums\RoleUser::Owner;

        $riwayatPembatalan = \Spatie\Activitylog\Models\Activity::where('subject_type', Order::class)
            ->where('subject_id', $order->id)
            ->where(function ($q) {
                $q->where('description', 'like', '%dibatalkan%')
                  ->orWhere('description', 'like', '%pengganti%');
            })
            ->latest()
            ->get();

        $penggantiCandidates = collect();
        if (!$order->parent_order_id && $order->status !== StatusOrder::Dibatalkan) {
            $penggantiCandidates = Order::where('cabang_id', $order->cabang_id)
                ->where('status', StatusOrder::Dibatalkan)
                ->where('id', '!=', $order->id)
                ->whereDoesntHave('childOrders')
                ->whereDate('created_at', now()->toDateString())
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'nomor_order', 'nama_pelanggan', 'total_bayar', 'created_at']);
        }

        return view('penjualan.show', compact('order', 'isOwner', 'riwayatPembatalan', 'penggantiCandidates'));
    }

    /**
     * Tentukan apakah harga per item tampil di struk cetakan (bukan halaman
     * detail Order — itu tidak disentuh, tetap tampil harga penuh untuk audit).
     * - Cabang tidak mengizinkan sembunyi harga -> SELALU tampil (behavior lama).
     * - Cabang mengizinkan tapi kasir tidak centang saat checkout -> sembunyi
     *   (default baru). Preferensi ini murni cetak, tidak disimpan ke order.
     */
    private function shouldShowItemPrice(Order $order): bool
    {
        if (!$order->cabang?->izinkan_sembunyi_harga_struk) {
            return true;
        }
        return request()->boolean('tampil_harga_struk', false);
    }

    public function struk(Order $order)
    {
        $this->pastikanBolehLihat($order);
        $order->load(['cabang', 'items.item']);
        $showItemPrice = $this->shouldShowItemPrice($order);
        return view('penjualan.struk', compact('order', 'showItemPrice'));
    }

    public function strukModal(Order $order)
    {
        $this->pastikanBolehLihat($order);
        $order->load(['cabang', 'items.item']);
        $copies = (int) request('copies', 1);
        $copies = in_array($copies, [1, 2]) ? $copies : 1;
        $showItemPrice = $this->shouldShowItemPrice($order);

        // Tombol opsional "Buat Klaim Loyalty" — cuma tampil kalau pelanggan
        // terdaftar (bukan walk-in) DAN ada program event_based aktif untuk
        // diklaim, supaya tidak jadi tombol mati (dead-end) di struk.
        $adaEventBasedAktif = $order->pelanggan_id
            && auth()->user()->can('loyalty.klaim.buat')
            && LoyaltyProgram::eventBased()->aktif()->exists();

        // 4.44 Poin 2 — tombol Label Produk (0 = cabang label mati / tidak ada item berlabel)
        $jumlahLabelCup = 0;
        if (auth()->user()->can('label_cup.print') && $order->cabang?->label_cup_aktif) {
            $labelSvc = app(\App\Services\CupLabelService::class);
            $jumlahLabelCup = $labelSvc->jumlah($labelSvc->untukOrder($order));
        }

        return view('penjualan._struk_modal_content', compact('order', 'copies', 'showItemPrice', 'adaEventBasedAktif', 'jumlahLabelCup'));
    }

    public function edit(Order $order)
    {
        abort_unless(auth()->user()->can('order.edit'), 403);

        if ($order->customer_type === 'internal') { // 4.49 — nilainya sudah tercatat di kas cabang tujuan
            return redirect()->route('penjualan.show', $order)->with('error', 'Kiriman/retur antar cabang tidak bisa diedit. Gunakan Tolak/Batalkan atau Retur.');
        }
        $isOwner = auth()->user()->role === \App\Enums\RoleUser::Owner;
        if (!$isOwner && in_array($order->status, [StatusOrder::Selesai, StatusOrder::Dibatalkan])) {
            return redirect()->route('penjualan.show', $order)
                ->with('error', "Order berstatus '{$order->status->label()}' tidak bisa diedit.");
        }

        $order->load(['items.item']);
        $tipesPembayaran = TipePembayaran::cases();

        return view('penjualan.edit', compact('order', 'tipesPembayaran'));
    }

    public function update(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('order.edit'), 403);

        if ($order->customer_type === 'internal') { // 4.49
            return back()->with('error', 'Kiriman/retur antar cabang tidak bisa diedit. Gunakan Tolak/Batalkan atau Retur.');
        }
        $isOwner = auth()->user()->role === \App\Enums\RoleUser::Owner;
        if (!$isOwner && in_array($order->status, [StatusOrder::Selesai, StatusOrder::Dibatalkan])) {
            return back()->with('error', "Order berstatus '{$order->status->label()}' tidak bisa diedit.");
        }

        $validated = $request->validate([
            'nama_pelanggan'    => 'nullable|string|max:100',
            'telepon_pelanggan' => 'nullable|string|max:20',
            'tipe_pembayaran'   => 'required|in:' . implode(',', array_column(TipePembayaran::cases(), 'value')),
            'diskon'            => 'required|numeric|min:0',
            'catatan'           => 'nullable|string|max:500',
        ]);

        $diskon     = (float) str_replace('.', '', $validated['diskon'] ?? 0);
        $totalBayar = max(0, (float) $order->total_harga - $diskon);

        $order->update([
            'nama_pelanggan'    => $validated['nama_pelanggan'],
            'telepon_pelanggan' => $validated['telepon_pelanggan'],
            'tipe_pembayaran'   => $validated['tipe_pembayaran'],
            'diskon'            => $diskon,
            'total_bayar'       => $totalBayar,
            'catatan'           => $validated['catatan'],
        ]);
        // OrderObserver::updated() fires → syncs total_bayar ke TransaksiKeuangan otomatis

        return redirect()->route('penjualan.show', $order)
            ->with('success', "Order {$order->nomor_order} berhasil diperbarui.");
    }

    public function batalkan(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('order.batalkan'), 403, 'Anda tidak memiliki izin untuk membatalkan order.');

        if ($order->status === StatusOrder::Dibatalkan) {
            return back()->with('error', 'Order sudah dibatalkan sebelumnya.');
        }

        // Rule: non-Owner cuma boleh batalkan order yang dibuat HARI INI.
        // Owner bypass (existing Gate::before juga sudah bypass abort_unless di atas,
        // tapi rule tanggal ini terpisah dari permission, jadi dicek manual).
        $isOwner    = auth()->user()->role === \App\Enums\RoleUser::Owner;
        $isHariSama = $order->created_at->isToday();

        if (!$isOwner && !$isHariSama) {
            return back()->with('error',
                'Order hanya bisa dibatalkan pada hari yang sama. Hubungi Owner untuk membatalkan order dari hari sebelumnya.');
        }

        $validated = $request->validate([
            'alasan_pembatalan_kategori' => 'required|string|max:50',
            'alasan_pembatalan_detail'   => 'nullable|string|max:500',
        ], [
            'alasan_pembatalan_kategori.required' => 'Alasan pembatalan wajib dipilih.',
        ]);

        try {
            $this->penjualanService->batalkan($order);

            $order->update([
                'alasan_pembatalan_kategori' => $validated['alasan_pembatalan_kategori'],
                'alasan_pembatalan_detail'   => $validated['alasan_pembatalan_detail'] ?? null,
            ]);

            $alasanText = $validated['alasan_pembatalan_kategori']
                . (!empty($validated['alasan_pembatalan_detail']) ? ' - ' . $validated['alasan_pembatalan_detail'] : '');

            $logMessage = (!$isHariSama && $isOwner)
                ? "Order dibatalkan (Bypassed by Owner) oleh {$request->user()->name}. Alasan: {$alasanText}"
                : "Order dibatalkan oleh {$request->user()->name}. Alasan: {$alasanText}";

            activity('Order')
                ->performedOn($order)
                ->causedBy(auth()->user())
                ->log($logMessage);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order berhasil dibatalkan.');
    }

    public function kembalikan(Order $order)
    {
        abort_unless(auth()->user()->can('order.kembalikan'), 403, 'Tidak ada izin kembalikan order.');

        if ($order->status !== StatusOrder::Dibatalkan) {
            return back()->with('error', 'Order bukan dalam status Dibatalkan.');
        }

        try {
            $this->penjualanService->kembalikan($order);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order berhasil dikembalikan ke status Selesai.');
    }

    /**
     * Cek apakah pelanggan yang sedang checkout di POS punya order yang
     * dibatalkan hari ini & belum ada penggantinya — dipakai POS untuk
     * popup konfirmasi "Order ini pengganti order #XXX?" (auto-detect).
     */
    public function cekPengganti(Request $request)
    {
        $authUser = auth()->user();
        $cabangId = session('active_cabang_id') ?? $authUser->defaultCabangId();

        $pelangganId = $request->input('pelanggan_id');
        $nama        = trim((string) $request->input('nama_pelanggan'));
        $telepon     = trim((string) $request->input('telepon_pelanggan'));

        $query = Order::where('cabang_id', $cabangId)
            ->where('status', StatusOrder::Dibatalkan)
            ->whereDate('created_at', today())
            ->whereDoesntHave('childOrders');

        if ($pelangganId) {
            $query->where('pelanggan_id', $pelangganId);
        } elseif ($nama !== '' && $telepon !== '') {
            $query->whereNull('pelanggan_id')
                ->where('nama_pelanggan', $nama)
                ->where('telepon_pelanggan', $telepon);
        } else {
            return response()->json(['has_pengganti' => false, 'orders' => []]);
        }

        $orders = $query->orderByDesc('id')->get(['id', 'nomor_order', 'total_bayar', 'created_at']);

        return response()->json([
            'has_pengganti' => $orders->isNotEmpty(),
            'orders' => $orders->map(fn ($o) => [
                'id'          => $o->id,
                'nomor_order' => $o->nomor_order,
                'total'       => number_format((float) $o->total_bayar, 0, ',', '.'),
                'jam'         => $o->created_at->format('H:i'),
            ])->values(),
        ]);
    }

    /**
     * Tombol manual "Tandai sebagai Pengganti" di detail order — backup
     * kalau auto-detect di POS ter-lewat (mis. kasir lupa centang popup).
     */
    public function tandaiPengganti(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('order.batalkan'), 403, 'Anda tidak memiliki izin untuk menandai order pengganti.');

        $validated = $request->validate([
            'parent_order_id' => 'required|exists:orders,id',
        ], [
            'parent_order_id.required' => 'Pilih order asli yang digantikan.',
        ]);

        if ((int) $validated['parent_order_id'] === $order->id) {
            return back()->with('error', 'Order tidak bisa jadi pengganti dirinya sendiri.');
        }

        $parentOrder = Order::where('id', $validated['parent_order_id'])
            ->where('cabang_id', $order->cabang_id)
            ->first();

        if (!$parentOrder) {
            return back()->with('error', 'Order asli tidak ditemukan di cabang yang sama.');
        }
        if ($parentOrder->status !== StatusOrder::Dibatalkan) {
            return back()->with('error', 'Order asli harus berstatus Dibatalkan.');
        }
        if ($parentOrder->childOrders()->exists()) {
            return back()->with('error', 'Order asli ini sudah punya order pengganti.');
        }

        $order->update(['parent_order_id' => $parentOrder->id]);

        activity('Order')
            ->performedOn($order)
            ->causedBy(auth()->user())
            ->log("Order {$order->nomor_order} ditandai manual sebagai pengganti order {$parentOrder->nomor_order} oleh {$request->user()->name}.");

        return back()->with('success', "Order ditandai sebagai pengganti {$parentOrder->nomor_order}.");
    }

    /**
     * 4.43 — detail order & struk: punya izin terkait order dan order milik
     * cabang yang boleh diakses (dulu semua role bisa membuka order mana pun).
     */
    private function pastikanBolehLihat(Order $order): void
    {
        abort_unless(auth()->user()->canAny(['order.view', 'order.create', 'antrian.lihat', 'antrian.kelola', 'keuangan.view', 'penjualan.laporan_transaksi.view']), 403);
        abort_unless(\App\Support\CabangAktif::bolehAkses($order->cabang_id), 403, 'Order ini milik cabang lain.');
    }

    public function destroy(Order $order, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('order.delete'), 403, 'Anda tidak memiliki akses untuk menghapus order.');
        abort_unless(\App\Support\CabangAktif::bolehAkses($order->cabang_id), 403, 'Order ini milik cabang lain.');

        // Hapus hanya menyembunyikan order (stok & kas TIDAK dikembalikan). Order yang
        // sudah dibayar wajib dibatalkan dulu supaya stok & kas kembali dengan benar.
        if (! in_array($order->status, [StatusOrder::Pending, StatusOrder::Dibatalkan], true)) {
            return back()->with('error', "Order {$order->nomor_order} sudah dibayar sehingga tidak bisa dihapus langsung. "
                . 'Klik Batalkan dulu (stok dan uang kas dikembalikan otomatis), setelah itu baru bisa dihapus bila perlu.');
        }

        try {
            $nomor   = $order->nomor_order;
            $deleted = $cascadeService->deleteOrderCascade($order);
            $ringkasan = collect($deleted)->filter()->map(fn($c, $k) => "{$c} {$k}")->join(', ');

            return redirect()->route('penjualan.index')
                ->with('success', "Order <strong>{$nomor}</strong> berhasil dihapus." . ($ringkasan ? " ({$ringkasan})" : '') . ' Data masih bisa dipulihkan di <a href="' . route('trash.index', ['model' => 'orders']) . '">Data Terhapus</a>.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menghapus order: ' . $e->getMessage());
        }
    }
}
