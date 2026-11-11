<?php

namespace App\Http\Controllers;

use App\Enums\TipeTransaksiKeuangan;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\BepFixedCostItem;
use App\Models\BepProduct;
use App\Models\BepReport;
use App\Models\BepSetting;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Penggajian;
use App\Models\TransaksiKeuangan;
use App\Services\BepCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BepController extends Controller
{
    public function __construct(private BepCalculationService $service) {}

    /**
     * Daftar semua BEP Settings
     */
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('bep.view'), 403);

        $user = auth()->user();
        $activeCabangId = session('active_cabang_id');

        // Bug9 FIX: withoutGlobalScopes — filter manual di bawah, konsisten dengan LaporanBepController
        $query = BepSetting::withoutGlobalScopes()->with(['cabang', 'products', 'fixedCostItems'])
            ->orderByDesc('periode');

        if (!$user->canAccessAllBranches()) {
            $cabangId = $activeCabangId ?? $user->defaultCabangId();
            $query->where('cabang_id', $cabangId);
        } elseif ($activeCabangId) {
            $query->where('cabang_id', $activeCabangId);
        } elseif ($request->filled('cabang_id')) {
            $query->where('cabang_id', $request->cabang_id);
        }

        if ($request->filled('periode')) {
            $query->where('periode', $request->periode);
        }

        $settings = $query->paginate(20)->withQueryString();

        // Stats
        $totalSettings  = $settings->total();
        $totalProduk    = BepProduct::whereIn('bep_setting_id', BepSetting::pluck('id'))->count();

        $periodeIni = date('Y-m');
        $laporanBulanIni = BepReport::withoutGlobalScopes()
            ->where('periode', $periodeIni)
            ->when($activeCabangId, fn($q) => $q->where('cabang_id', $activeCabangId))
            ->first();

        $cabangs = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        return view('bep.index', compact('settings', 'cabangs', 'totalSettings', 'totalProduk', 'laporanBulanIni', 'periodeIni'));
    }

    /**
     * Form setting BEP untuk cabang & periode tertentu
     */
    public function setting(Request $request)
    {
        abort_unless(auth()->user()->can('bep.view'), 403);

        $user = auth()->user();
        $activeCabangId = session('active_cabang_id');
        $cabangId = $request->get('cabang_id', $activeCabangId ?? $user->defaultCabangId());
        $periode  = $request->get('periode', date('Y-m'));

        $setting = BepSetting::with(['fixedCostItems', 'products'])
            ->where('cabang_id', $cabangId)
            ->where('periode', $periode)
            ->first();

        $cabang  = Cabang::find($cabangId);
        $cabangs = $user->canAccessAllBranches() ? Cabang::aktif()->orderBy('nama_cabang')->get() : collect();

        // Bug5 FIX: expand pilihan kategori (VARCHAR fleksibel setelah migration)
        $kategoriBiayaTetap = [
            'gaji'         => 'Gaji Karyawan',
            'sewa'         => 'Sewa Gedung',
            'depresiasi'   => 'Penyusutan Aset',
            'listrik'      => 'Listrik & Air',
            'internet'     => 'Internet & Telepon',
            'gas'          => 'Gas & Bahan Bakar',
            'transportasi' => 'Transportasi',
            'asuransi'     => 'Asuransi',
            'marketing'    => 'Marketing & Promosi',
            'lainnya'      => 'Lainnya',
        ];

        return view('bep.setting', compact(
            'setting', 'cabang', 'cabangs', 'cabangId', 'periode', 'kategoriBiayaTetap'
        ));
    }

    /**
     * Simpan / update BepSetting
     */
    public function storeSetting(Request $request)
    {
        abort_unless(auth()->user()->can('bep.manage'), 403);

        $request->validate([
            'cabang_id' => 'required|exists:cabangs,id',
            'periode'   => 'required|date_format:Y-m',
            'catatan'   => 'nullable|string',
        ]);

        $setting = BepSetting::updateOrCreate(
            ['cabang_id' => $request->cabang_id, 'periode' => $request->periode],
            [
                'catatan'    => $request->catatan,
                'created_by' => auth()->id(),
            ]
        );

        return redirect()->route('bep.setting', ['cabang_id' => $setting->cabang_id, 'periode' => $setting->periode])
            ->with('success', 'Setting BEP berhasil disimpan.');
    }

    /**
     * Tambah / update item biaya tetap
     */
    public function storeFixedCost(Request $request, BepSetting $setting)
    {
        abort_unless(auth()->user()->can('bep.manage'), 403);

        $request->validate([
            'nama_komponen' => 'required|string|max:100',
            'kategori'      => 'required|string|max:50',
            'jumlah'        => 'required|numeric|min:0',
            'catatan'       => 'nullable|string',
        ]);

        BepFixedCostItem::create([
            'bep_setting_id' => $setting->id,
            'nama_komponen'  => $request->nama_komponen,
            'kategori'       => $request->kategori,
            'jumlah'         => $request->jumlah,
            'catatan'        => $request->catatan,
        ]);

        // Hitung ulang BEP
        $this->service->hitungAtauUpdateBep($setting);

        return redirect()->route('bep.setting', ['cabang_id' => $setting->cabang_id, 'periode' => $setting->periode])
            ->with('success', 'Biaya tetap berhasil ditambahkan dan BEP dihitung ulang.');
    }

    /**
     * Hapus item biaya tetap
     */
    public function deleteFixedCost(BepFixedCostItem $item)
    {
        abort_unless(auth()->user()->can('bep.manage'), 403);

        $setting = $item->bepSetting;
        $item->delete();

        // Hitung ulang BEP
        $this->service->hitungAtauUpdateBep($setting);

        return redirect()->route('bep.setting', ['cabang_id' => $setting->cabang_id, 'periode' => $setting->periode])
            ->with('success', 'Biaya tetap berhasil dihapus.');
    }

    /**
     * Tambah / update produk BEP
     */
    public function storeProduct(Request $request, BepSetting $setting)
    {
        abort_unless(auth()->user()->can('bep.manage'), 403);

        $request->validate([
            'nama_produk'           => 'required|string|max:100',
            'tipe'                  => 'required|in:produk,jasa_giling',
            'harga_jual_per_unit'   => 'required|numeric|min:0',
            'biaya_variabel_per_unit' => 'required|numeric|min:0',
            'target_penjualan_unit' => 'nullable|numeric|min:0',
            'catatan'               => 'nullable|string',
        ]);

        $product = BepProduct::create([
            'bep_setting_id'          => $setting->id,
            'nama_produk'             => $request->nama_produk,
            'tipe'                    => $request->tipe,
            'harga_jual_per_unit'     => $request->harga_jual_per_unit,
            'biaya_variabel_per_unit' => $request->biaya_variabel_per_unit,
            'target_penjualan_unit'   => $request->target_penjualan_unit ?? 0,
            'catatan'                 => $request->catatan,
        ]);

        // Hitung BEP untuk produk ini
        $product->hitungBep();
        $product->save();

        return redirect()->route('bep.setting', ['cabang_id' => $setting->cabang_id, 'periode' => $setting->periode])
            ->with('success', 'Produk/jasa berhasil ditambahkan dan BEP dihitung.');
    }

    /**
     * Hapus produk BEP
     */
    public function deleteProduct(BepProduct $product)
    {
        abort_unless(auth()->user()->can('bep.manage'), 403);

        $setting = $product->bepSetting;
        $product->delete();

        return redirect()->route('bep.setting', ['cabang_id' => $setting->cabang_id, 'periode' => $setting->periode])
            ->with('success', 'Produk/jasa berhasil dihapus.');
    }

    /**
     * Isi otomatis biaya tetap & produk dari data sistem.
     * Produk diambil dari penjualan aktual bulan ini (fallback ke master produk_jadi).
     */
    public function autoFill(BepSetting $setting): \Illuminate\Http\RedirectResponse
    {
        abort_unless(auth()->user()->can('bep.manage'), 403);

        $cabangId = $setting->cabang_id;
        $periode  = $setting->periode; // format: Y-m
        [$tahun, $bulan] = explode('-', $periode);

        $komponenBiayaTetap = 0;
        $productsCreated    = 0;
        $productsSkipped    = 0;
        $noOrderData        = false;

        DB::transaction(function () use (
            $setting, $cabangId, $periode, $tahun, $bulan,
            &$komponenBiayaTetap, &$productsCreated, &$productsSkipped, &$noOrderData
        ) {

            // ── 1. BIAYA TETAP: Gaji dari Penggajian ────────────────────────
            $totalGaji = Penggajian::where('cabang_id', $cabangId)
                ->where('periode', $periode)
                ->sum('total_gaji');

            if ($totalGaji > 0) {
                BepFixedCostItem::updateOrCreate(
                    ['bep_setting_id' => $setting->id, 'nama_komponen' => 'Gaji Karyawan (Auto)'],
                    ['kategori' => 'gaji', 'jumlah' => $totalGaji,
                     'catatan' => 'Diambil otomatis dari data penggajian ' . $periode]
                );
                $komponenBiayaTetap++;
            }

            // ── 2. BIAYA TETAP: Penyusutan Aset ─────────────────────────────
            $totalDepresiasi = AssetDepreciation::whereHas('asset', fn($q) => $q->where('lokasi_id', $cabangId))
                ->where('periode', $periode)
                ->sum('jumlah_penyusutan');

            if ($totalDepresiasi > 0) {
                BepFixedCostItem::updateOrCreate(
                    ['bep_setting_id' => $setting->id, 'nama_komponen' => 'Penyusutan Aset (Auto)'],
                    ['kategori' => 'depresiasi', 'jumlah' => $totalDepresiasi,
                     'catatan' => 'Diambil otomatis dari data penyusutan aset ' . $periode]
                );
                $komponenBiayaTetap++;
            }

            // ── 3. BIAYA TETAP: Sewa & Operasional dari Transaksi Keuangan ──
            $sewaRoot = \App\Models\KategoriTransaksi::where('kode', 'SEWA')->first();
            $opsRoot  = \App\Models\KategoriTransaksi::where('kode', 'OPS')->first();

            $sewaOpsGrup = [];
            if ($sewaRoot) {
                $sewaIds = collect([$sewaRoot->id])
                    ->merge($sewaRoot->children()->pluck('id'))
                    ->toArray();
                $sewaOpsGrup[] = ['ids' => $sewaIds, 'komponen' => 'Sewa Gedung (Auto)', 'kategori' => 'sewa'];
            }
            if ($opsRoot) {
                $opsIds = collect([$opsRoot->id])
                    ->merge($opsRoot->children()->pluck('id'))
                    ->toArray();
                $sewaOpsGrup[] = ['ids' => $opsIds, 'komponen' => 'Biaya Operasional (Auto)', 'kategori' => 'lainnya'];
            }

            foreach ($sewaOpsGrup as $grup) {
                $total = TransaksiKeuangan::withoutGlobalScopes()
                    ->where('cabang_id', $cabangId)
                    ->where('tipe', TipeTransaksiKeuangan::Pengeluaran)
                    ->whereIn('kategori_id', $grup['ids'])
                    ->where('tanggal_transaksi', 'like', $periode . '%')
                    ->whereNull('deleted_at')
                    ->sum('jumlah');

                if ($total > 0) {
                    BepFixedCostItem::updateOrCreate(
                        ['bep_setting_id' => $setting->id, 'nama_komponen' => $grup['komponen']],
                        ['kategori' => $grup['kategori'], 'jumlah' => $total,
                         'catatan'  => 'Diambil otomatis dari transaksi keuangan ' . $periode]
                    );
                    $komponenBiayaTetap++;
                }
            }

            // ── 4. PRODUK: dari penjualan aktual bulan ini (ENHANCED) ────────
            $itemsTerjual = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.cabang_id', $cabangId)
                ->whereYear('orders.tanggal_order', (int) $tahun)
                ->whereMonth('orders.tanggal_order', (int) $bulan)
                ->whereNotIn('orders.status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('orders.deleted_at')
                ->whereNull('orders.deleted_at')
                ->whereNull('order_items.deleted_at')
                ->whereNotNull('order_items.item_id')
                ->select('order_items.item_id', DB::raw('SUM(order_items.qty) as total_qty'))
                ->groupBy('order_items.item_id')
                ->get();

            if ($itemsTerjual->isEmpty()) {
                // Fallback: tidak ada order bulan ini → pakai master produk_jual aktif
                // (Tahap 2.5 D'mentai: 'produk_jadi' -> 'produk_jual', 1:1 rename;
                // sisa logic 'lainnya'->jasa_giling di bawah TETAP tidak disentuh,
                // itu bagian dari gap CLAUDE.md 4.1 yang ditunda Tahap 5/6).
                $noOrderData = true;
                $produkJadi  = Item::where('tipe', 'produk_jual')->where('is_active', true)->get();

                foreach ($produkJadi as $item) {
                    $hargaJual     = (float) ($item->harga_jual ?? 0);
                    $biayaVariabel = (float) ($item->harga_beli_terakhir ?? 0);

                    if ($hargaJual <= 0) { $productsSkipped++; continue; }

                    BepProduct::updateOrCreate(
                        ['bep_setting_id' => $setting->id, 'nama_produk' => $item->nama_item],
                        [
                            'tipe'                    => 'produk',
                            'harga_jual_per_unit'     => $hargaJual,
                            'biaya_variabel_per_unit' => $biayaVariabel,
                            'target_penjualan_unit'   => 0,
                            'catatan'                 => "Dari master barang — tidak ada penjualan {$periode}. Sesuaikan target unit.",
                        ]
                    );
                    $productsCreated++;
                }
            } else {
                foreach ($itemsTerjual as $row) {
                    $item = Item::find($row->item_id);
                    if (!$item) continue;

                    // Tipe BEP: 'lainnya' → jasa_giling, semua lain → produk
                    $tipeValue = $item->tipe instanceof \BackedEnum
                        ? $item->tipe->value
                        : (string) $item->tipe;
                    $tipeBep = $tipeValue === 'lainnya' ? 'jasa_giling' : 'produk';

                    $hargaJual     = (float) ($item->harga_jual ?? 0);
                    $biayaVariabel = (float) ($item->harga_beli_terakhir ?? 0);

                    if ($hargaJual <= 0) {
                        $productsSkipped++;
                        continue;
                    }

                    BepProduct::updateOrCreate(
                        ['bep_setting_id' => $setting->id, 'nama_produk' => $item->nama_item],
                        [
                            'tipe'                    => $tipeBep,
                            'harga_jual_per_unit'     => $hargaJual,
                            'biaya_variabel_per_unit' => $biayaVariabel,
                            'target_penjualan_unit'   => (float) $row->total_qty,
                            'catatan'                 => "Harga dari master barang • Target dari penjualan {$periode}",
                        ]
                    );
                    $productsCreated++;
                }
            }

            // ── 5. Jasa Giling default (updateOrCreate — tidak override jika sudah diisi) ──
            BepProduct::updateOrCreate(
                ['bep_setting_id' => $setting->id, 'nama_produk' => 'Jasa Giling'],
                [
                    'tipe'                    => 'jasa_giling',
                    'harga_jual_per_unit'     => 0,
                    'biaya_variabel_per_unit' => 0,
                    'target_penjualan_unit'   => 0,
                    'catatan'                 => 'Isi harga tarif/kg dan target kg jasa giling per bulan.',
                ]
            );

            // ── 6. Hitung ulang BEP untuk semua produk ───────────────────────
            $this->service->hitungAtauUpdateBep($setting);
        });

        // ── Flash message informatif ─────────────────────────────────────────
        $msg = "Auto-fill selesai! Biaya tetap: {$komponenBiayaTetap} komponen. "
             . "Produk: {$productsCreated} item di-update/create otomatis";

        if ($productsSkipped > 0) {
            $msg .= ", {$productsSkipped} di-skip (harga jual 0)";
        }

        if ($noOrderData) {
            $msg .= ". Tidak ada penjualan {$periode} — menggunakan master produk_jadi sebagai fallback. "
                  . "Sesuaikan target unit secara manual.";
        } else {
            $msg .= ". Target unit diambil dari penjualan aktual {$periode}.";
        }

        return redirect()
            ->route('bep.setting', ['cabang_id' => $setting->cabang_id, 'periode' => $setting->periode])
            ->with('success', $msg);
    }

    /**
     * Hitung ulang semua BEP dalam setting
     */
    public function hitungUlang(BepSetting $setting)
    {
        abort_unless(auth()->user()->can('bep.manage'), 403);

        $this->service->hitungAtauUpdateBep($setting);

        return redirect()->route('bep.setting', ['cabang_id' => $setting->cabang_id, 'periode' => $setting->periode])
            ->with('success', 'BEP berhasil dihitung ulang.');
    }

}

