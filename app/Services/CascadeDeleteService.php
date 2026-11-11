<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\Item;
use App\Models\Karyawan;
use App\Models\Order;
use App\Models\Pelanggan;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Asset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CascadeDeleteService
{
    /**
     * Preview dampak hapus cabang — hitung berapa record yang akan di-soft-delete.
     * Hanya menghitung yang AKAN dihapus (belum soft-deleted, punya soft-delete support).
     */
    public function previewCabangDelete(Cabang $cabang): array
    {
        $id = $cabang->id;

        return [
            'karyawan'           => DB::table('karyawans')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'penggajian'         => DB::table('penggajians')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'absensi'            => DB::table('absensis')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'face_attendance'    => DB::table('face_attendances')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'absen_device'       => DB::table('absen_devices')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'order'              => DB::table('orders')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'purchase_order'     => DB::table('purchase_orders')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'stok'               => DB::table('stocks')->where('lokasi_id', $id)->whereNull('deleted_at')->count(),
            'stock_request'      => DB::table('stock_requests')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'stock_transfer'     => DB::table('stock_transfers')
                ->where(fn($q) => $q->where('dari_lokasi_id', $id)->orWhere('ke_lokasi_id', $id))
                ->whereNull('deleted_at')->count(),
            'transaksi_keuangan' => DB::table('transaksi_keuangans')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'aset'               => DB::table('assets')->where('lokasi_id', $id)->whereNull('deleted_at')->count(),
            'shift'              => DB::table('shifts')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
            'hari_libur'         => DB::table('hari_liburs')->where('cabang_id', $id)->whereNull('deleted_at')->count(),
        ];
    }

    /**
     * Eksekusi soft-delete cascade untuk cabang beserta seluruh relasinya.
     * Menggunakan DB::table()->update(['deleted_at' => now()]) untuk memastikan soft-delete
     * yang benar tanpa bergantung pada Eloquent scope (aman, atomic, dan bypass global scope).
     *
     * Return: array jumlah record yang ter-soft-delete per entitas.
     */
    public function deleteCabangCascade(Cabang $cabang): array
    {
        return DB::transaction(function () use ($cabang) {
            $id  = $cabang->id;
            $now = Carbon::now();
            $deleted = [];

            // ── 1. Leaf: Penggajian, Absensi, Face Attendance ──────────────
            $deleted['penggajian']      = DB::table('penggajians')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['absensi']         = DB::table('absensis')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['face_attendance'] = DB::table('face_attendances')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            // ── 2. Transaksi: Order, PO, Stok, Keuangan ───────────────────
            $deleted['order']              = DB::table('orders')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['purchase_order']     = DB::table('purchase_orders')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['stok']               = DB::table('stocks')
                ->where('lokasi_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['stock_request']      = DB::table('stock_requests')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['stock_transfer']     = DB::table('stock_transfers')
                ->where(fn($q) => $q->where('dari_lokasi_id', $id)->orWhere('ke_lokasi_id', $id))
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['transaksi_keuangan'] = DB::table('transaksi_keuangans')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            // ── 3. HR & Aset ───────────────────────────────────────────────
            $deleted['absen_device']       = DB::table('absen_devices')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['aset']               = DB::table('assets')
                ->where('lokasi_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['shift']              = DB::table('shifts')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['hari_libur']         = DB::table('hari_liburs')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            // ── 4. Karyawan (setelah dependensinya selesai) ───────────────
            $deleted['karyawan']           = DB::table('karyawans')
                ->where('cabang_id', $id)->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            // ── 5. Cabang itu sendiri (via Eloquent agar observer & audit terpanggil) ──
            $cabang->delete();
            $deleted['cabang'] = 1;

            // Log operasi cascade sebagai satu entry audit
            activity('CascadeCabangDelete')
                ->causedBy(auth()->user())
                ->withProperties([
                    'cabang_id'    => $cabang->id,
                    'nama_cabang'  => $cabang->nama_cabang,
                    'deleted_counts' => array_filter($deleted, fn($v) => $v > 0),
                ])
                ->log("Cascade soft-delete Cabang #{$cabang->id} ({$cabang->nama_cabang})");

            return array_filter($deleted, fn($v) => $v > 0);
        });
    }

    /**
     * Cascade restore semua data terkait cabang — mirror dari deleteCabangCascade().
     * Hanya restore record yang deleted_at IS NOT NULL (di-soft-delete bersama cabang).
     * Pakai kolom yang sama persis dengan deleteCabangCascade():
     * - stocks/assets: lokasi_id
     * - stock_transfers: dari_lokasi_id / ke_lokasi_id
     */
    public function restoreCabangCascade(Cabang $cabang): array
    {
        return DB::transaction(function () use ($cabang) {
            $id       = $cabang->id;
            $restored = [];

            // ── 1. Cabang itu sendiri ──────────────────────────────────────
            DB::table('cabangs')->where('id', $id)->update(['deleted_at' => null]);
            $restored['cabang'] = 1;

            // ── 2. Leaf: Penggajian, Absensi, Face Attendance ──────────────
            $restored['penggajian']      = DB::table('penggajians')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['absensi']         = DB::table('absensis')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['face_attendance'] = DB::table('face_attendances')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            // ── 3. Transaksi: Order, PO, Stok, Keuangan ───────────────────
            $restored['order']              = DB::table('orders')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['purchase_order']     = DB::table('purchase_orders')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['stok']               = DB::table('stocks')
                ->where('lokasi_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['stock_request']      = DB::table('stock_requests')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['stock_transfer']     = DB::table('stock_transfers')
                ->where(fn($q) => $q->where('dari_lokasi_id', $id)->orWhere('ke_lokasi_id', $id))
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['transaksi_keuangan'] = DB::table('transaksi_keuangans')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            // ── 4. HR & Aset ───────────────────────────────────────────────
            $restored['absen_device']       = DB::table('absen_devices')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['aset']               = DB::table('assets')
                ->where('lokasi_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['shift']              = DB::table('shifts')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['hari_libur']         = DB::table('hari_liburs')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            // ── 5. Karyawan (restore setelah dependensi) ──────────────────
            $restored['karyawan']           = DB::table('karyawans')
                ->where('cabang_id', $id)->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            activity('CascadeCabangRestore')
                ->causedBy(auth()->user())
                ->withProperties([
                    'cabang_id'       => $id,
                    'nama_cabang'     => $cabang->nama_cabang,
                    'restored_counts' => array_filter($restored, fn($v) => $v > 0),
                ])
                ->log("Cascade restore Cabang #{$id} ({$cabang->nama_cabang})");

            return array_filter($restored, fn($v) => $v > 0);
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ITEM (Master Barang)
    // Child dengan deleted_at: order_items, purchase_order_items, stocks
    // Child tanpa deleted_at (skip): stock_request_items, stock_transfer_items, stock_movements
    // ══════════════════════════════════════════════════════════════════════════

    public function previewItemDelete(Item $item): array
    {
        $id = $item->id;
        return [
            'order_item'           => DB::table('order_items')->where('item_id', $id)->whereNull('deleted_at')->count(),
            'purchase_order_item'  => DB::table('purchase_order_items')->where('item_id', $id)->whereNull('deleted_at')->count(),
            'stok'                 => DB::table('stocks')->where('item_id', $id)->whereNull('deleted_at')->count(),
        ];
    }

    public function deleteItemCascade(Item $item): array
    {
        return DB::transaction(function () use ($item) {
            $id  = $item->id;
            $now = Carbon::now();
            $deleted = [];

            $deleted['order_item']          = DB::table('order_items')->where('item_id', $id)->whereNull('deleted_at')->update(['deleted_at' => $now]);
            $deleted['purchase_order_item'] = DB::table('purchase_order_items')->where('item_id', $id)->whereNull('deleted_at')->update(['deleted_at' => $now]);
            $deleted['stok']                = DB::table('stocks')->where('item_id', $id)->whereNull('deleted_at')->update(['deleted_at' => $now]);

            $item->delete();
            $deleted['item'] = 1;

            activity('CascadeItemDelete')->causedBy(auth()->user())
                ->withProperties(['item_id' => $id, 'nama_item' => $item->nama_item, 'deleted_counts' => array_filter($deleted, fn($v) => $v > 0)])
                ->log("Cascade soft-delete Item #{$id} ({$item->nama_item})");

            return array_filter($deleted, fn($v) => $v > 0);
        });
    }

    public function restoreItemCascade(Item $item): array
    {
        return DB::transaction(function () use ($item) {
            $id       = $item->id;
            $restored = [];

            DB::table('items')->where('id', $id)->update(['deleted_at' => null]);
            $restored['item'] = 1;

            $restored['order_item']          = DB::table('order_items')->where('item_id', $id)->whereNotNull('deleted_at')->update(['deleted_at' => null]);
            $restored['purchase_order_item'] = DB::table('purchase_order_items')->where('item_id', $id)->whereNotNull('deleted_at')->update(['deleted_at' => null]);
            $restored['stok']                = DB::table('stocks')->where('item_id', $id)->whereNotNull('deleted_at')->update(['deleted_at' => null]);

            activity('CascadeItemRestore')->causedBy(auth()->user())
                ->withProperties(['item_id' => $id, 'nama_item' => $item->nama_item, 'restored_counts' => array_filter($restored, fn($v) => $v > 0)])
                ->log("Cascade restore Item #{$id} ({$item->nama_item})");

            return array_filter($restored, fn($v) => $v > 0);
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SUPPLIER
    // Child dengan deleted_at: purchase_orders
    // ══════════════════════════════════════════════════════════════════════════

    public function previewSupplierDelete(Supplier $supplier): array
    {
        return [
            'purchase_order' => DB::table('purchase_orders')->where('supplier_id', $supplier->id)->whereNull('deleted_at')->count(),
        ];
    }

    public function deleteSupplierCascade(Supplier $supplier): array
    {
        return DB::transaction(function () use ($supplier) {
            $id  = $supplier->id;
            $now = Carbon::now();
            $deleted = [];

            $deleted['purchase_order'] = DB::table('purchase_orders')->where('supplier_id', $id)->whereNull('deleted_at')->update(['deleted_at' => $now]);

            $supplier->delete();
            $deleted['supplier'] = 1;

            activity('CascadeSupplierDelete')->causedBy(auth()->user())
                ->withProperties(['supplier_id' => $id, 'nama' => $supplier->nama_supplier, 'deleted_counts' => array_filter($deleted, fn($v) => $v > 0)])
                ->log("Cascade soft-delete Supplier #{$id} ({$supplier->nama_supplier})");

            return array_filter($deleted, fn($v) => $v > 0);
        });
    }

    public function restoreSupplierCascade(Supplier $supplier): array
    {
        return DB::transaction(function () use ($supplier) {
            $id       = $supplier->id;
            $restored = [];

            DB::table('suppliers')->where('id', $id)->update(['deleted_at' => null]);
            $restored['supplier'] = 1;

            $restored['purchase_order'] = DB::table('purchase_orders')->where('supplier_id', $id)->whereNotNull('deleted_at')->update(['deleted_at' => null]);

            activity('CascadeSupplierRestore')->causedBy(auth()->user())
                ->withProperties(['supplier_id' => $id, 'restored_counts' => array_filter($restored, fn($v) => $v > 0)])
                ->log("Cascade restore Supplier #{$id} ({$supplier->nama_supplier})");

            return array_filter($restored, fn($v) => $v > 0);
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // KARYAWAN
    // Child dengan deleted_at: absensis, face_attendances, penggajians
    // Child tanpa deleted_at (skip): cutis, saldo_cutis, evaluations
    // ══════════════════════════════════════════════════════════════════════════

    public function previewKaryawanDelete(Karyawan $karyawan): array
    {
        $id = $karyawan->id;
        return [
            'absensi'        => DB::table('absensis')->where('karyawan_id', $id)->whereNull('deleted_at')->count(),
            'face_attendance'=> DB::table('face_attendances')->where('karyawan_id', $id)->whereNull('deleted_at')->count(),
            'penggajian'     => DB::table('penggajians')->where('karyawan_id', $id)->whereNull('deleted_at')->count(),
        ];
    }

    public function deleteKaryawanCascade(Karyawan $karyawan): array
    {
        return DB::transaction(function () use ($karyawan) {
            $id  = $karyawan->id;
            $now = Carbon::now();
            $deleted = [];

            $deleted['absensi']         = DB::table('absensis')->where('karyawan_id', $id)->whereNull('deleted_at')->update(['deleted_at' => $now]);
            $deleted['face_attendance'] = DB::table('face_attendances')->where('karyawan_id', $id)->whereNull('deleted_at')->update(['deleted_at' => $now]);
            $deleted['penggajian']      = DB::table('penggajians')->where('karyawan_id', $id)->whereNull('deleted_at')->update(['deleted_at' => $now]);

            // Putus tautan user_id tanpa menghapus user (user bisa tetap aktif)
            if ($karyawan->user_id) {
                DB::table('karyawans')->where('id', $id)->update(['user_id' => null]);
            }

            // Ubah status ke keluar + soft delete
            $karyawan->status         = 'keluar';
            $karyawan->tanggal_keluar = today();
            $karyawan->save();
            $karyawan->delete();
            $deleted['karyawan'] = 1;

            activity('CascadeKaryawanDelete')->causedBy(auth()->user())
                ->withProperties(['karyawan_id' => $id, 'nama' => $karyawan->nama_lengkap, 'deleted_counts' => array_filter($deleted, fn($v) => $v > 0)])
                ->log("Cascade soft-delete Karyawan #{$id} ({$karyawan->nama_lengkap})");

            return array_filter($deleted, fn($v) => $v > 0);
        });
    }

    public function restoreKaryawanCascade(Karyawan $karyawan): array
    {
        return DB::transaction(function () use ($karyawan) {
            $id       = $karyawan->id;
            $restored = [];

            DB::table('karyawans')->where('id', $id)->update(['deleted_at' => null]);
            $restored['karyawan'] = 1;

            $restored['absensi']         = DB::table('absensis')->where('karyawan_id', $id)->whereNotNull('deleted_at')->update(['deleted_at' => null]);
            $restored['face_attendance'] = DB::table('face_attendances')->where('karyawan_id', $id)->whereNotNull('deleted_at')->update(['deleted_at' => null]);
            $restored['penggajian']      = DB::table('penggajians')->where('karyawan_id', $id)->whereNotNull('deleted_at')->update(['deleted_at' => null]);

            activity('CascadeKaryawanRestore')->causedBy(auth()->user())
                ->withProperties(['karyawan_id' => $id, 'restored_counts' => array_filter($restored, fn($v) => $v > 0)])
                ->log("Cascade restore Karyawan #{$id} ({$karyawan->nama_lengkap})");

            return array_filter($restored, fn($v) => $v > 0);
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PELANGGAN
    // Child dengan deleted_at: orders, order_items (via orders)
    // ══════════════════════════════════════════════════════════════════════════

    public function previewPelangganDelete(Pelanggan $pelanggan): array
    {
        $id = $pelanggan->id;
        $orderIds = DB::table('orders')->where('pelanggan_id', $id)->whereNull('deleted_at')->pluck('id');
        return [
            'order'      => $orderIds->count(),
            'order_item' => DB::table('order_items')->whereIn('order_id', $orderIds)->whereNull('deleted_at')->count(),
        ];
    }

    public function deletePelangganCascade(Pelanggan $pelanggan): array
    {
        return DB::transaction(function () use ($pelanggan) {
            $id  = $pelanggan->id;
            $now = Carbon::now();
            $deleted = [];

            $orderIds = DB::table('orders')->where('pelanggan_id', $id)->whereNull('deleted_at')->pluck('id');

            if ($orderIds->isNotEmpty()) {
                $deleted['order_item'] = DB::table('order_items')->whereIn('order_id', $orderIds)->whereNull('deleted_at')->update(['deleted_at' => $now]);
            }

            $deleted['order'] = DB::table('orders')->where('pelanggan_id', $id)->whereNull('deleted_at')->update(['deleted_at' => $now]);

            $pelanggan->delete();
            $deleted['pelanggan'] = 1;

            activity('CascadePelangganDelete')->causedBy(auth()->user())
                ->withProperties(['pelanggan_id' => $id, 'nama' => $pelanggan->nama_pelanggan, 'deleted_counts' => array_filter($deleted, fn($v) => $v > 0)])
                ->log("Cascade soft-delete Pelanggan #{$id} ({$pelanggan->nama_pelanggan})");

            return array_filter($deleted, fn($v) => $v > 0);
        });
    }

    public function restorePelangganCascade(Pelanggan $pelanggan): array
    {
        return DB::transaction(function () use ($pelanggan) {
            $id       = $pelanggan->id;
            $restored = [];

            DB::table('pelanggans')->where('id', $id)->update(['deleted_at' => null]);
            $restored['pelanggan'] = 1;

            $orderIds = DB::table('orders')->where('pelanggan_id', $id)->whereNotNull('deleted_at')->pluck('id');

            $restored['order'] = DB::table('orders')->where('pelanggan_id', $id)->whereNotNull('deleted_at')->update(['deleted_at' => null]);

            if ($orderIds->isNotEmpty()) {
                $restored['order_item'] = DB::table('order_items')->whereIn('order_id', $orderIds)->whereNotNull('deleted_at')->update(['deleted_at' => null]);
            }

            activity('CascadePelangganRestore')->causedBy(auth()->user())
                ->withProperties(['pelanggan_id' => $id, 'restored_counts' => array_filter($restored, fn($v) => $v > 0)])
                ->log("Cascade restore Pelanggan #{$id} ({$pelanggan->nama_pelanggan})");

            return array_filter($restored, fn($v) => $v > 0);
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ASSET
    // Semua child (depreciations, disposals, maintenances, mutations) TIDAK punya deleted_at.
    // Mereka dipertahankan sebagai audit record keuangan — hanya asset itu sendiri yang dihapus.
    // ══════════════════════════════════════════════════════════════════════════

    public function previewAssetDelete(Asset $asset): array
    {
        $id = $asset->id;
        return [
            'catatan_penyusutan' => DB::table('asset_depreciations')->where('asset_id', $id)->count(),
            'riwayat_maintenance'=> DB::table('asset_maintenances')->where('asset_id', $id)->count(),
            'riwayat_mutasi'     => DB::table('asset_mutations')->where('asset_id', $id)->count(),
            'catatan_disposal'   => DB::table('asset_disposals')->where('asset_id', $id)->count(),
        ];
    }

    public function deleteAssetCascade(Asset $asset): array
    {
        return DB::transaction(function () use ($asset) {
            $deleted = [];

            // Riwayat penyusutan/maintenance/mutasi/disposal dipertahankan di DB (no deleted_at).
            // Hanya aset itu sendiri yang di-soft-delete.
            $asset->delete();
            $deleted['aset'] = 1;

            activity('CascadeAssetDelete')->causedBy(auth()->user())
                ->withProperties(['asset_id' => $asset->id, 'nama' => $asset->nama_aset, 'deleted_counts' => $deleted])
                ->log("Cascade soft-delete Asset #{$asset->id} ({$asset->nama_aset})");

            return $deleted;
        });
    }

    public function restoreAssetCascade(Asset $asset): array
    {
        return DB::transaction(function () use ($asset) {
            DB::table('assets')->where('id', $asset->id)->update(['deleted_at' => null]);

            activity('CascadeAssetRestore')->causedBy(auth()->user())
                ->withProperties(['asset_id' => $asset->id])
                ->log("Cascade restore Asset #{$asset->id} ({$asset->nama_aset})");

            return ['aset' => 1];
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // USER
    // Session di-hard-delete (tidak ada deleted_at). Activity log DIPERTAHANKAN.
    // Karyawan yang terhubung (karyawans.user_id) → null-kan link saja, jangan hapus karyawan.
    // Kepala cabang → null-kan di cabangs.kepala_cabang_id.
    // ══════════════════════════════════════════════════════════════════════════

    public function previewUserDelete(User $user): array
    {
        $id = $user->id;
        return [
            'session'          => DB::table('sessions')->where('user_id', $id)->count(),
            'cabang_assigned'  => DB::table('cabang_user')->where('user_id', $id)->count(),
            'kepala_cabang_di' => DB::table('cabangs')->where('kepala_cabang_id', $id)->whereNull('deleted_at')->count(),
            'profil_karyawan'  => DB::table('karyawans')->where('user_id', $id)->whereNull('deleted_at')->count(),
        ];
    }

    public function deleteUserCascade(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $id      = $user->id;
            $deleted = [];

            // Cabang pivot — detach assignments
            $deleted['cabang_assignment'] = DB::table('cabang_user')->where('user_id', $id)->delete();

            // Null-kan kepala_cabang_id di cabangs (bukan soft delete cabang)
            DB::table('cabangs')->where('kepala_cabang_id', $id)->update(['kepala_cabang_id' => null]);

            // Null-kan user_id di karyawans (jangan hapus karyawan)
            DB::table('karyawans')->where('user_id', $id)->update(['user_id' => null]);

            // Hard-delete sessions (tidak perlu restore)
            $deleted['session'] = DB::table('sessions')->where('user_id', $id)->delete();

            // Soft-delete user
            $user->delete();
            $deleted['user'] = 1;

            activity('CascadeUserDelete')->causedBy(auth()->user())
                ->withProperties(['user_id' => $id, 'nama' => $user->name, 'role' => $user->role?->value, 'deleted_counts' => array_filter($deleted, fn($v) => $v > 0)])
                ->log("Cascade soft-delete User #{$id} ({$user->name})");

            return array_filter($deleted, fn($v) => $v > 0);
        });
    }

    public function restoreUserCascade(User $user): array
    {
        return DB::transaction(function () use ($user) {
            DB::table('users')->where('id', $user->id)->update(['deleted_at' => null]);

            // Sessions tidak perlu di-restore (user login ulang saja).
            // karyawans.user_id dan cabangs.kepala_cabang_id harus di-reassign manual.

            activity('CascadeUserRestore')->causedBy(auth()->user())
                ->withProperties(['user_id' => $user->id])
                ->log("Cascade restore User #{$user->id} ({$user->name})");

            return ['user' => 1];
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PURCHASE ORDER — child: purchase_order_items (punya deleted_at)
    // ══════════════════════════════════════════════════════════════════════════

    public function previewPurchaseOrderDelete(PurchaseOrder $po): array
    {
        return [
            'item_po'            => DB::table('purchase_order_items')
                ->where('purchase_order_id', $po->id)
                ->whereNull('deleted_at')
                ->count(),
            'transaksi_keuangan' => DB::table('transaksi_keuangans')
                ->where('referensi_type', 'purchase_order')
                ->where('referensi_id', $po->id)
                ->whereNull('deleted_at')
                ->count(),
        ];
    }

    public function deletePurchaseOrderCascade(PurchaseOrder $po): array
    {
        return DB::transaction(function () use ($po) {
            $now     = Carbon::now();
            $deleted = [];

            $deleted['item_po'] = DB::table('purchase_order_items')
                ->where('purchase_order_id', $po->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['transaksi_keuangan'] = DB::table('transaksi_keuangans')
                ->where('referensi_type', 'purchase_order')
                ->where('referensi_id', $po->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $po->delete();
            $deleted['purchase_order'] = 1;

            activity('CascadePurchaseOrderDelete')
                ->causedBy(auth()->user())
                ->withProperties([
                    'po_id'          => $po->id,
                    'nomor_po'       => $po->nomor_po,
                    'deleted_counts' => array_filter($deleted, fn($v) => $v > 0),
                ])
                ->log("Cascade soft-delete PurchaseOrder #{$po->id} ({$po->nomor_po})");

            return array_filter($deleted, fn($v) => $v > 0);
        });
    }

    public function restorePurchaseOrderCascade(PurchaseOrder $po): array
    {
        return DB::transaction(function () use ($po) {
            $restored = [];

            DB::table('purchase_orders')->where('id', $po->id)->update(['deleted_at' => null]);
            $restored['purchase_order'] = 1;

            $restored['item_po'] = DB::table('purchase_order_items')
                ->where('purchase_order_id', $po->id)
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['transaksi_keuangan'] = DB::table('transaksi_keuangans')
                ->where('referensi_type', 'purchase_order')
                ->where('referensi_id', $po->id)
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            activity('CascadePurchaseOrderRestore')
                ->causedBy(auth()->user())
                ->withProperties(['po_id' => $po->id, 'nomor_po' => $po->nomor_po])
                ->log("Cascade restore PurchaseOrder #{$po->id} ({$po->nomor_po})");

            return array_filter($restored, fn($v) => $v > 0);
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ORDER (Penjualan) — child: order_items (punya deleted_at)
    // ══════════════════════════════════════════════════════════════════════════

    public function previewOrderDelete(Order $order): array
    {
        return [
            'item_order'         => DB::table('order_items')
                ->where('order_id', $order->id)
                ->whereNull('deleted_at')
                ->count(),
            'transaksi_keuangan' => DB::table('transaksi_keuangans')
                ->where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->whereNull('deleted_at')
                ->count(),
        ];
    }

    public function deleteOrderCascade(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $now     = Carbon::now();
            $deleted = [];

            $deleted['item_order'] = DB::table('order_items')
                ->where('order_id', $order->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $deleted['transaksi_keuangan'] = DB::table('transaksi_keuangans')
                ->where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now]);

            $order->delete();
            $deleted['order'] = 1;

            activity('CascadeOrderDelete')
                ->causedBy(auth()->user())
                ->withProperties([
                    'order_id'           => $order->id,
                    'nomor_order'        => $order->nomor_order,
                    'deleted_counts'     => array_filter($deleted, fn($v) => $v > 0),
                ])
                ->log("Cascade soft-delete Order #{$order->id} ({$order->nomor_order})");

            return array_filter($deleted, fn($v) => $v > 0);
        });
    }

    public function restoreOrderCascade(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $restored = [];

            DB::table('orders')->where('id', $order->id)->update(['deleted_at' => null]);
            $restored['order'] = 1;

            $restored['item_order'] = DB::table('order_items')
                ->where('order_id', $order->id)
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            $restored['transaksi_keuangan'] = DB::table('transaksi_keuangans')
                ->where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->whereNotNull('deleted_at')
                ->update(['deleted_at' => null]);

            activity('CascadeOrderRestore')
                ->causedBy(auth()->user())
                ->withProperties(['order_id' => $order->id, 'nomor_order' => $order->nomor_order])
                ->log("Cascade restore Order #{$order->id} ({$order->nomor_order})");

            return array_filter($restored, fn($v) => $v > 0);
        });
    }

    /**
     * Cleanup data orphan: restore semua record yang masih deleted_at IS NOT NULL
     * padahal parent cabang-nya sudah aktif (deleted_at NULL).
     * Dipanggil sekali via route /admin/cleanup-orphan-cascade.
     */
    public function cleanupOrphanSoftDeleted(): array
    {
        $fixed = [];

        // Tabel dengan cabang_id langsung
        $tablesViaCabangId = [
            'karyawans', 'penggajians', 'absensis', 'face_attendances',
            'absen_devices', 'orders', 'purchase_orders',
            'stock_requests', 'transaksi_keuangans', 'shifts', 'hari_liburs',
        ];

        $activeCabangSubquery = fn($q) => $q->select('id')->from('cabangs')->whereNull('deleted_at');

        foreach ($tablesViaCabangId as $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) continue;
            $count = DB::table($table)
                ->whereNotNull('deleted_at')
                ->whereIn('cabang_id', $activeCabangSubquery)
                ->update(['deleted_at' => null]);
            if ($count > 0) $fixed[$table] = $count;
        }

        // Tabel yang pakai lokasi_id (bukan cabang_id)
        foreach (['stocks', 'assets'] as $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) continue;
            $count = DB::table($table)
                ->whereNotNull('deleted_at')
                ->whereIn('lokasi_id', $activeCabangSubquery)
                ->update(['deleted_at' => null]);
            if ($count > 0) $fixed[$table] = $count;
        }

        // stock_transfers: 2 kolom lokasi
        if (Schema::hasColumn('stock_transfers', 'deleted_at')) {
            $count = DB::table('stock_transfers')
                ->whereNotNull('deleted_at')
                ->where(function ($q) use ($activeCabangSubquery) {
                    $q->whereIn('dari_lokasi_id', $activeCabangSubquery)
                      ->orWhereIn('ke_lokasi_id', $activeCabangSubquery);
                })
                ->update(['deleted_at' => null]);
            if ($count > 0) $fixed['stock_transfers'] = $count;
        }

        // order_items: parent adalah orders (yang deleted_at IS NULL = aktif)
        if (Schema::hasColumn('order_items', 'deleted_at')) {
            $activeOrderIds = fn($q) => $q->select('id')->from('orders')->whereNull('deleted_at');
            $count = DB::table('order_items')
                ->whereNotNull('deleted_at')
                ->whereIn('order_id', $activeOrderIds)
                ->update(['deleted_at' => null]);
            if ($count > 0) $fixed['order_items'] = $count;
        }

        // purchase_order_items: parent adalah purchase_orders
        if (Schema::hasColumn('purchase_order_items', 'deleted_at')) {
            $activePoIds = fn($q) => $q->select('id')->from('purchase_orders')->whereNull('deleted_at');
            $count = DB::table('purchase_order_items')
                ->whereNotNull('deleted_at')
                ->whereIn('purchase_order_id', $activePoIds)
                ->update(['deleted_at' => null]);
            if ($count > 0) $fixed['purchase_order_items'] = $count;
        }

        return $fixed;
    }
}
