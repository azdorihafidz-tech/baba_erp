<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\Kas;
use App\Models\Karyawan;
use App\Models\KategoriTransaksi;
use App\Models\Order;
use App\Models\Pelanggan;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\CascadeDeleteService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class TrashController extends Controller
{
    /**
     * Model yang support soft-delete dan bisa di-restore.
     * Key = identifier di URL, Value = FQCN model.
     */
    private array $models = [
        'orders'             => \App\Models\Order::class,
        'setorans'           => \App\Models\Setoran::class, // Tahap 5 D'mentai
        'purchase_orders'    => \App\Models\PurchaseOrder::class,
        'suppliers'          => \App\Models\Supplier::class,
        'absensis'           => \App\Models\Absensi::class,
        'karyawans'          => \App\Models\Karyawan::class,
        'cabangs'            => \App\Models\Cabang::class,
        'shifts'             => \App\Models\Shift::class,
        'hari_liburs'        => \App\Models\HariLibur::class,
        'penggajians'        => \App\Models\Penggajian::class,
        // Fase 4: User, Cabang, Master Pendukung
        'users'              => \App\Models\User::class,
        'absen_devices'      => \App\Models\AbsenDevice::class,
        'item_categories'    => \App\Models\ItemCategory::class,
        'pelanggans'         => \App\Models\Pelanggan::class,
        // Fase 3: Stok, Master Barang, Aset
        'stocks'             => \App\Models\Stock::class,
        'stock_requests'     => \App\Models\StockRequest::class,
        'stock_transfers'    => \App\Models\StockTransfer::class,
        'items'              => \App\Models\Item::class,
        'assets'             => \App\Models\Asset::class,
        // Keuangan
        'transaksi_keuangans'  => \App\Models\TransaksiKeuangan::class,
        'kas'                  => \App\Models\Kas::class,
        'kategori_transaksis'  => \App\Models\KategoriTransaksi::class,
        'recurring_transaksis' => \App\Models\RecurringTransaksi::class,
        // Stok — batch FIFO
        'stock_batches'      => \App\Models\StockBatch::class,
        // HR — Cuti
        'cutis'              => \App\Models\Cuti::class,
        // Akuntansi — Chart of Accounts
        'chart_of_accounts'  => \App\Models\ChartOfAccount::class,
        // Program Loyalty
        'loyalty_programs'   => \App\Models\LoyaltyProgram::class,
        'loyalty_pencapaian' => \App\Models\LoyaltyPencapaian::class,
        'loyalty_klaims'     => \App\Models\LoyaltyKlaim::class,
        // Tahap 2 D'mentai — Fitur Varian (DB+Model saja, UI di Tahap 3)
        'item_attributes'       => \App\Models\ItemAttribute::class,
        'item_attribute_values' => \App\Models\ItemAttributeValue::class,
        'item_variants'         => \App\Models\ItemVariant::class,
        // Tahap 3 D'mentai — Split Payment
        'order_payments'        => \App\Models\OrderPayment::class,
    ];

    /**
     * Relasi yang di-eager-load per model untuk halaman Detail — murni untuk
     * menghindari N+1 query, bukan syarat (lazy loading tidak di-disable
     * secara global di project ini).
     */
    private array $detailRelations = [
        \App\Models\Order::class             => ['cabang', 'pelanggan', 'kasir', 'items.item', 'kas'],
        \App\Models\Kas::class                => ['cabang'],
        \App\Models\TransaksiKeuangan::class  => ['cabang', 'kas', 'kategoriDinamis', 'createdBy'],
        \App\Models\PurchaseOrder::class      => ['cabang', 'supplier', 'items.item', 'approvedBy', 'createdBy'],
        \App\Models\Stock::class              => ['item', 'lokasi'],
        \App\Models\Item::class               => ['category'],
        \App\Models\Pelanggan::class          => [],
        \App\Models\Karyawan::class           => ['cabang', 'shift', 'user'],
        \App\Models\Asset::class              => ['kategori', 'lokasi', 'disposal'],
        \App\Models\Cuti::class                => ['karyawan', 'cabang', 'approvedBy'],
    ];

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('lihat_data_terhapus'), 403, 'Anda tidak memiliki akses ke Data Terhapus.');

        $activeModel = $request->get('model', 'orders');
        $modelClass  = $this->models[$activeModel] ?? null;

        $rows = collect();
        if ($modelClass && class_exists($modelClass)) {
            try {
                $rows = $modelClass::onlyTrashed()->latest('deleted_at')->paginate(30)->withQueryString();
            } catch (\Exception $e) {
                $rows = collect();
            }
        }

        // Tahap 7 D'mentai (Bug 3b) — counter badge per tab, supaya user
        // langsung tahu di kategori mana ada data terhapus tanpa harus
        // klik satu-satu (root cause kebingungan Bug 3: default tab
        // "Orders" kelihatan kosong padahal data ada di tab "Master Barang").
        $counts = $this->hitungJumlahTerhapus();

        return view('trash.index', compact('rows', 'activeModel', 'counts'));
    }

    /** @return array<string,int> jumlah baris ter-soft-delete per model, key sama dgn $this->models. */
    private function hitungJumlahTerhapus(): array
    {
        $counts = [];
        foreach ($this->models as $key => $modelClass) {
            if (! class_exists($modelClass)) continue;
            try {
                $counts[$key] = $modelClass::onlyTrashed()->count();
            } catch (\Exception $e) {
                $counts[$key] = 0;
            }
        }
        return $counts;
    }

    public function detail(Request $request, string $model, int $id)
    {
        abort_unless(auth()->user()->can('lihat_data_terhapus'), 403, 'Anda tidak memiliki akses ke Data Terhapus.');

        $modelClass = $this->models[$model] ?? null;
        abort_if(!$modelClass, 404);

        $relations = $this->detailRelations[$modelClass] ?? [];
        $record    = $modelClass::onlyTrashed()->with($relations)->findOrFail($id);

        $deletedByUser = $record->deleted_by ? User::find($record->deleted_by) : null;

        $activities = Activity::where('subject_type', $modelClass)
            ->where('subject_id', $id)
            ->with('causer')
            ->latest()
            ->get();

        // TransaksiKeuangan.referensi_type disimpan sebagai string logis
        // ('order'/'purchase_order'/'kas'/'stock_movement'), BUKAN FQCN, dan
        // tidak ada morphMap terdaftar — morphTo() bawaan akan error kalau
        // dipanggil langsung (coba `new order()`). Resolve manual di sini,
        // jangan pernah akses $record->referensi di view.
        $referensi = null;
        if ($record instanceof TransaksiKeuangan && $record->referensi_type && $record->referensi_id) {
            $referensi = $this->resolveReferensi($record->referensi_type, $record->referensi_id);
        }

        // Kas: tampilkan 10 riwayat transaksi terakhir (row TransaksiKeuangan
        // terkait Kas ini pada umumnya TIDAK ikut ter-soft-delete saat Kas
        // dihapus — bukan cascade, jadi query biasa).
        $kasRiwayat = null;
        if ($record instanceof Kas) {
            $kasRiwayat = TransaksiKeuangan::where('kas_id', $record->id)->latest('tanggal_transaksi')->limit(10)->get();
        }

        return view('trash.detail', compact('record', 'model', 'modelClass', 'deletedByUser', 'activities', 'referensi', 'kasRiwayat'));
    }

    private function resolveReferensi(string $type, int $id): ?array
    {
        try {
            $result = match ($type) {
                'order'           => Order::withTrashed()->find($id),
                'purchase_order'  => PurchaseOrder::withTrashed()->find($id),
                'kas'             => Kas::withTrashed()->find($id),
                'stock_movement'  => \App\Models\StockMovement::find($id),
                default           => null,
            };
        } catch (\Throwable $e) {
            $result = null;
        }

        return $result ? ['type' => $type, 'record' => $result] : ['type' => $type, 'record' => null];
    }

    public function restore(Request $request, string $model, int $id, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('restore_data_terhapus'), 403, 'Anda tidak memiliki akses untuk restore data.');

        $modelClass = $this->models[$model] ?? null;
        abort_if(!$modelClass, 404);

        $record = $modelClass::onlyTrashed()->findOrFail($id);

        // Cascade restore per jenis model
        $cascadeResult = null;
        $cascadeMessage = null;

        if ($record instanceof Cabang) {
            $cascadeResult  = $cascadeService->restoreCabangCascade($record);
            $cascadeMessage = "Cabang <strong>{$record->nama_cabang}</strong> beserta data terkait berhasil dipulihkan.";
        } elseif ($record instanceof Item) {
            $cascadeResult  = $cascadeService->restoreItemCascade($record);
            $cascadeMessage = "Item <strong>{$record->nama_item}</strong> beserta data terkait berhasil dipulihkan.";
        } elseif ($record instanceof Supplier) {
            $cascadeResult  = $cascadeService->restoreSupplierCascade($record);
            $cascadeMessage = "Supplier <strong>{$record->nama_supplier}</strong> beserta data terkait berhasil dipulihkan.";
        } elseif ($record instanceof Karyawan) {
            $cascadeResult  = $cascadeService->restoreKaryawanCascade($record);
            $cascadeMessage = "Karyawan <strong>{$record->nama_lengkap}</strong> beserta data terkait berhasil dipulihkan.";
        } elseif ($record instanceof Pelanggan) {
            $cascadeResult  = $cascadeService->restorePelangganCascade($record);
            $cascadeMessage = "Pelanggan <strong>{$record->nama_pelanggan}</strong> beserta data terkait berhasil dipulihkan.";
        } elseif ($record instanceof Asset) {
            $cascadeResult  = $cascadeService->restoreAssetCascade($record);
            $cascadeMessage = "Aset <strong>{$record->nama_aset}</strong> berhasil dipulihkan.";
        } elseif ($record instanceof User) {
            $cascadeResult  = $cascadeService->restoreUserCascade($record);
            $cascadeMessage = "User <strong>{$record->name}</strong> berhasil dipulihkan. Perlu assign ulang ke cabang & karyawan.";
        } elseif ($record instanceof PurchaseOrder) {
            $cascadeResult  = $cascadeService->restorePurchaseOrderCascade($record);
            $cascadeMessage = "Purchase Order <strong>{$record->nomor_po}</strong> beserta item-itemnya berhasil dipulihkan.";
        } elseif ($record instanceof Order) {
            $cascadeResult  = $cascadeService->restoreOrderCascade($record);
            $cascadeMessage = "Order <strong>{$record->nomor_order}</strong> beserta item-itemnya berhasil dipulihkan.";
        } elseif ($record instanceof TransaksiKeuangan) {
            $record->restore();
            $cascadeResult  = [];
            $cascadeMessage = "Transaksi <strong>{$record->nomor_transaksi}</strong> berhasil dipulihkan."
                . ($record->kas_id ? ' Saldo kas telah diperbarui.' : '');
        } elseif ($record instanceof Kas) {
            $record->restore();
            $cascadeResult  = [];
            $cascadeMessage = "Kas <strong>{$record->nama_kas}</strong> berhasil dipulihkan.";
        } elseif ($record instanceof KategoriTransaksi) {
            $record->restore();
            $cascadeResult  = [];
            $cascadeMessage = "Kategori <strong>{$record->nama}</strong> berhasil dipulihkan.";
        }

        if ($cascadeResult !== null) {
            $ringkasan = collect($cascadeResult)->filter()->map(fn($c, $k) => "{$c} {$k}")->join(', ');
            return back()->with('success', $cascadeMessage . ($ringkasan ? " ({$ringkasan})" : ''));
        }

        // Default: restore single record (no cascade needed)
        $record->restore();

        activity('TrashRestore')
            ->causedBy(auth()->user())
            ->performedOn($record)
            ->withProperties(['restored_id' => $id, 'model' => $model])
            ->log("Restored {$model} #{$id}");

        return back()->with('success', ucfirst(str_replace('_', ' ', $model)) . " #{$id} berhasil dipulihkan.");
    }

    public function forceDestroy(string $model, int $id)
    {
        abort_unless(auth()->user()->can('hapus_permanen_data'), 403, 'Anda tidak memiliki akses untuk hapus permanen.');

        $modelClass = $this->models[$model] ?? null;
        abort_if(!$modelClass, 404);

        $record = $modelClass::onlyTrashed()->findOrFail($id);

        activity('TrashForceDelete')
            ->causedBy(auth()->user())
            ->performedOn($record)
            ->withProperties(['force_deleted_id' => $id, 'model' => $model, 'data' => $record->toArray()])
            ->log("Force deleted {$model} #{$id}");

        $record->forceDelete();

        return back()->with('success', ucfirst(str_replace('_', ' ', $model)) . " #{$id} dihapus permanen.");
    }

    public function modelList(): array
    {
        return array_keys($this->models);
    }
}
