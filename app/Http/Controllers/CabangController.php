<?php

namespace App\Http\Controllers;

use App\Enums\TipeCabang;
use App\Http\Requests\CabangRequest;
use App\Models\Absensi;
use App\Models\AbsenDevice;
use App\Models\Asset;
use App\Models\BepReport;
use App\Models\BepSetting;
use App\Models\Cabang;
use App\Models\Cuti;
use App\Models\Evaluation;
use App\Models\EvaluationPeriod;
use App\Models\FaceAttendance;
use App\Models\Kas;
use App\Models\Karyawan;
use App\Models\Order;
use App\Models\Shift;
use App\Models\Penggajian;
use App\Models\PurchaseOrder;
use App\Models\Stock;
use App\Models\StockRequest;
use App\Models\StockTransfer;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Services\CascadeDeleteService;
use Illuminate\Http\Request;

class CabangController extends Controller
{
    /**
     * Daftar semua cabang
     */
    public function index(Request $request)
    {
        $query = Cabang::with('kepalaCabang')
            ->withCount('karyawans');

        // Filter by tipe
        if ($request->filled('tipe')) {
            $query->where('tipe', $request->tipe);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'aktif');
        }

        // Search
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('nama_cabang', 'like', "%{$keyword}%")
                  ->orWhere('kode_cabang', 'like', "%{$keyword}%")
                  ->orWhere('alamat', 'like', "%{$keyword}%");
            });
        }

        $cabangs = $query->orderBy('tipe')->orderBy('nama_cabang')->paginate(15)->withQueryString();

        $stats = [
            'total'        => Cabang::count(),
            'aktif'        => Cabang::where('is_active', true)->count(),
            'cabang'       => Cabang::where('tipe', TipeCabang::Cabang)->count(),
            'gudang_pusat' => Cabang::where('tipe', TipeCabang::GudangPusat)->count(),
            'head_office'  => Cabang::where('tipe', TipeCabang::HeadOffice)->count(),
        ];

        return view('cabang.index', compact('cabangs', 'stats'));
    }

    /**
     * Form tambah cabang baru
     */
    public function create()
    {
        $users = User::where('is_active', true)
            ->whereIn('role', ['manajer_cabang', 'admin_gudang', 'owner', 'admin_pusat'])
            ->orderBy('name')
            ->get();

        $tipes = TipeCabang::cases();
        $cabangSumber = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('cabang.create', compact('users', 'tipes', 'cabangSumber'));
    }

    /**
     * Simpan cabang baru
     */
    public function store(CabangRequest $request)
    {
        $data = $request->validated();
        $salinDari = $data['salin_dari_cabang_id'] ?? null;
        unset($data['salin_dari_cabang_id']);
        $data['kode_cabang'] = strtoupper($data['kode_cabang']);
        if (($data['tipe'] ?? null) !== 'cabang') { $data['jenis_toko'] = null; } // BABA Tahap 3: jenis toko hanya untuk cabang penjualan
        $data['is_active'] = true;
        // BABA Tahap 3 — toko parfum: kasir tanpa pilihan Dine-in/Frozen/nomor meja (istilah dimsum)
        if (! empty($data['jenis_toko'])) {
            // ditetapkan (bukan hanya ditambah): formulir ikut mengirim nilai bawaan dimsum (dine-in aktif)
            $data = array_merge($data, ['dine_in_aktif' => false, 'takeaway_aktif' => true, 'frozen_aktif' => false, 'nomor_meja_aktif' => false,
                'pilih_tipe_transaksi' => false, 'tipe_transaksi_default' => 'takeaway']);
        }

        $cabang = Cabang::create($data);
        if (! empty($data['jenis_toko'])) {
            app(\App\Services\KasBawaanService::class)->pastikan($cabang); // kasir toko langsung bisa menerima tunai/transfer/QRIS
        }
        if ($salinDari) {
            $this->salinPengaturanBarang((int) $salinDari, $cabang);
        }

        // Auto-create 3 shift default untuk cabang baru
        $defaultShifts = [
            ['nama_shift' => 'Shift 1 (Pagi)',  'jam_masuk' => '08:00:00', 'jam_keluar' => '16:00:00'],
            ['nama_shift' => 'Shift 2 (Sore)',  'jam_masuk' => '16:00:00', 'jam_keluar' => '00:00:00'],
            ['nama_shift' => 'Shift 3 (Malam)', 'jam_masuk' => '00:00:00', 'jam_keluar' => '08:00:00'],
        ];
        foreach ($defaultShifts as $shiftData) {
            Shift::create([
                'cabang_id'             => $cabang->id,
                'nama_shift'            => $shiftData['nama_shift'],
                'jam_masuk'             => $shiftData['jam_masuk'],
                'jam_keluar'            => $shiftData['jam_keluar'],
                'toleransi_telat_menit' => 15,
                'is_active'             => true,
            ]);
        }

        // Jika ada kepala cabang, auto-assign ke cabang ini
        if ($cabang->kepala_cabang_id) {
            $user = User::find($cabang->kepala_cabang_id);
            if ($user && !$user->cabangs()->where('cabangs.id', $cabang->id)->exists()) {
                $user->cabangs()->attach($cabang->id, ['is_default' => false]);
            }
        }

        return redirect()
            ->route('cabang.index')
            ->with('success', "Cabang <strong>{$cabang->nama_cabang}</strong> berhasil ditambahkan.");
    }

    /**
     * Salin data barang dari cabang lain ke cabang baru (CLAUDE.md 4.39):
     * semua bahan, produk, resep & Bumbu Pusat milik cabang sumber (kode sama)
     * + Qty Minimum. Data lama yang belum dipecah (tanpa cabang) memakai
     * pengaturan item_cabang + qty minimum lama. Stok & transaksi TIDAK disalin.
     */
    private function salinPengaturanBarang(int $sumberId, Cabang $baru): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($sumberId, $baru) {
            app(\App\Services\SalinDataCabangService::class)->salin($sumberId, $baru->id);

            // Data lama tanpa cabang (belum dipecah)
            $lamaIds = \App\Models\Item::whereNull('cabang_id')->pluck('id');
            foreach (\App\Models\ItemCabang::where('cabang_id', $sumberId)->whereIn('item_id', $lamaIds)->get() as $row) {
                \App\Models\ItemCabang::updateOrCreate(
                    ['item_id' => $row->item_id, 'cabang_id' => $baru->id],
                    ['is_active' => $row->is_active, 'harga_override' => $row->harga_override, 'harga_beli' => $row->harga_beli]
                );
            }
            foreach (\App\Models\Stock::where('lokasi_id', $sumberId)->whereIn('item_id', $lamaIds)->where('qty_minimum', '>', 0)->get() as $st) {
                $stok = new \App\Models\Stock(['item_id' => $st->item_id, 'lokasi_id' => $baru->id, 'qty' => 0, 'qty_minimum' => $st->qty_minimum]);
                $stok->updated_at = now();
                $stok->save();
            }
        });
    }

    /**
     * Detail cabang (opsional — redirect ke edit)
     */
    public function show(Cabang $cabang)
    {
        return redirect()->route('cabang.edit', $cabang);
    }

    /**
     * Form edit cabang
     */
    public function edit(Cabang $cabang)
    {
        $users = User::where('is_active', true)
            ->whereIn('role', ['manajer_cabang', 'admin_gudang', 'owner', 'admin_pusat'])
            ->orderBy('name')
            ->get();

        $tipes = TipeCabang::cases();

        // User yang sudah assign ke cabang ini
        $assignedUsers = $cabang->users()->get();

        // Shifts cabang ini
        $shifts = $cabang->shifts;

        // 4.44 Poin 2 — kategori yang punya produk jual, untuk pilihan Label Produk
        $kategoriLabelOptions = \App\Models\ItemCategory::whereHas('items', fn ($q) => $q->where('tipe', 'produk_jual'))
            ->orderBy('nama_kategori')->get(['id', 'kode_kategori', 'nama_kategori']);

        return view('cabang.edit', compact('cabang', 'users', 'tipes', 'assignedUsers', 'shifts', 'kategoriLabelOptions'));
    }

    /**
     * Update cabang
     */
    public function update(CabangRequest $request, Cabang $cabang)
    {
        $data = $request->validated();
        $data['kode_cabang'] = strtoupper($data['kode_cabang']);
        if (($data['tipe'] ?? null) !== 'cabang') { $data['jenis_toko'] = null; } // BABA Tahap 3: jenis toko hanya untuk cabang penjualan
        // 4.44 Poin 2 — semua checkbox kategori label kosong = tidak ada kategori berlabel
        if ($request->boolean('label_cup_form')) {
            $data['label_cup_kategori'] = array_values($request->input('label_cup_kategori', []));
        }
        // 4.49 Tahap D — geser label: kosong = 0; kolom belum ada (migration belum jalan) = abaikan
        if (! \Illuminate\Support\Facades\Schema::hasColumn('cabangs', 'kirim_cabang_aktif')) {
            unset($data['kirim_cabang_aktif']);
        }
        foreach (['label_cup_geser_x', 'label_cup_geser_y'] as $k) {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('cabangs', $k)) {
                unset($data[$k]);
            } elseif (array_key_exists($k, $data)) {
                $data[$k] = (float) ($data[$k] ?? 0);
            }
        }

        $oldKepalaCabangId = $cabang->kepala_cabang_id;
        $cabang->update($data);

        // Jika kepala cabang berubah, assign kepala baru ke cabang ini
        $kepalaBaru = $data['kepala_cabang_id'] ?? null;
        if ($kepalaBaru && $kepalaBaru != $oldKepalaCabangId) {
            $user = User::find($kepalaBaru);
            if ($user && !$user->cabangs()->where('cabangs.id', $cabang->id)->exists()) {
                $user->cabangs()->attach($cabang->id, ['is_default' => false]);
            }
        }

        // Update shifts jika ada data shift dari form
        foreach ($request->input('shifts', []) as $shiftData) {
            if (empty($shiftData['id'])) continue;
            Shift::where('id', (int) $shiftData['id'])
                ->where('cabang_id', $cabang->id)
                ->update([
                    'nama_shift'            => $shiftData['nama_shift'] ?? 'Shift',
                    'jam_masuk'             => $shiftData['jam_masuk'] ?? '08:00',
                    'jam_keluar'            => $shiftData['jam_keluar'] ?? '16:00',
                    'toleransi_telat_menit' => (int) ($shiftData['toleransi_telat_menit'] ?? 15),
                    'is_active'             => isset($shiftData['is_active']) ? 1 : 0,
                ]);
        }

        return redirect()
            ->route('cabang.index')
            ->with('success', "Cabang <strong>{$cabang->nama_cabang}</strong> berhasil diperbarui.");
    }

    /**
     * Hapus cabang beserta seluruh data terkait (soft-delete cascade).
     * Semua data terkait (karyawan, stok, absensi, dll) ikut di-soft-delete dalam satu transaksi.
     */
    public function destroy(Cabang $cabang, CascadeDeleteService $cascadeService)
    {
        $nama = $cabang->nama_cabang;

        try {
            $deleted = $cascadeService->deleteCabangCascade($cabang);

            $labels = [
                'karyawan'           => 'Karyawan',
                'penggajian'         => 'Penggajian',
                'absensi'            => 'Absensi',
                'face_attendance'    => 'Absensi Wajah',
                'absen_device'       => 'Device Absen',
                'order'              => 'Order',
                'purchase_order'     => 'Purchase Order',
                'stok'               => 'Stok',
                'stock_request'      => 'Permintaan Stok',
                'stock_transfer'     => 'Transfer Stok',
                'transaksi_keuangan' => 'Transaksi Keuangan',
                'aset'               => 'Aset',
                'shift'              => 'Shift',
                'hari_libur'         => 'Hari Libur',
                'cabang'             => 'Cabang',
            ];

            $ringkasan = collect($deleted)
                ->map(fn($count, $key) => "{$count} " . ($labels[$key] ?? $key))
                ->filter()
                ->join(', ');

            return redirect()
                ->route('cabang.index')
                ->with('success', "Cabang <strong>{$nama}</strong> beserta data terkait berhasil dihapus. ({$ringkasan})");

        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus cabang <strong>{$nama}</strong>: " . $e->getMessage());
        }
    }

    /**
     * Toggle aktif / nonaktif cabang
     */
    public function toggleAktif(Cabang $cabang)
    {
        $cabang->update(['is_active' => !$cabang->is_active]);

        $status = $cabang->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Cabang <strong>{$cabang->nama_cabang}</strong> berhasil {$status}.");
    }

    /**
     * Kelola user yang di-assign ke cabang ini
     */
    public function assignUser(Request $request, Cabang $cabang)
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'is_default' => 'sometimes|boolean',
        ]);

        $userId = $request->user_id;
        $isDefault = $request->boolean('is_default', false);

        if ($cabang->users()->where('users.id', $userId)->exists()) {
            return back()->with('warning', 'User sudah terdaftar di cabang ini.');
        }

        $cabang->users()->attach($userId, ['is_default' => $isDefault]);

        $user = User::find($userId);
        return back()->with('success', "<strong>{$user->name}</strong> berhasil ditambahkan ke cabang ini.");
    }

    /**
     * Hapus user dari cabang
     */
    public function removeUser(Request $request, Cabang $cabang)
    {
        $request->validate(['user_id' => 'required|exists:users,id']);

        $cabang->users()->detach($request->user_id);

        return back()->with('success', 'User berhasil dihapus dari cabang ini.');
    }
}
