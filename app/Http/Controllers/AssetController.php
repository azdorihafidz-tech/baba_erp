<?php

namespace App\Http\Controllers;

use App\Enums\KondisiAset;
use App\Enums\MetodePenyusutan;
use App\Enums\StatusAset;
use App\Exports\AsetExport;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use App\Models\AssetDisposal;
use App\Models\AssetMaintenance;
use App\Models\AssetMutation;
use App\Models\Cabang;
use App\Notifications\AsetNotification;
use App\Services\AssetDepreciationService;
use App\Services\CascadeDeleteService;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AssetController extends Controller
{
    public function __construct(private AssetDepreciationService $service) {}

    /**
     * Query builder + filter yang aktif — DIREUSE oleh index() dan export()
     * supaya logic filter (lokasi/kategori/kondisi/status/search) tidak
     * pernah ditulis 2x dan export selalu ikut filter yang sedang aktif.
     */
    private function buildFilteredQuery(Request $request): Builder
    {
        $user = auth()->user();
        $activeCabangId = session('active_cabang_id');

        $query = Asset::with(['kategori', 'lokasi'])
            ->orderByDesc('created_at');

        // Filter lokasi
        if (!$user->canAccessAllBranches()) {
            $cabangId = $activeCabangId ?? $user->defaultCabangId();
            $query->where('lokasi_id', $cabangId);
        } elseif ($activeCabangId) {
            $query->where('lokasi_id', $activeCabangId);
        } elseif ($request->filled('lokasi_id')) {
            $query->where('lokasi_id', $request->lokasi_id);
        }

        if ($request->filled('kategori_id')) {
            $query->where('kategori_aset_id', $request->kategori_id);
        }
        if ($request->filled('kondisi')) {
            $query->where('kondisi', $request->kondisi);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search multi-field — TAMBAHAN, tidak mengganti filter lokasi/kategori/kondisi/status di atas
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('kode_aset', 'like', "%{$search}%")
                  ->orWhere('nama_aset', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Daftar aset
     */
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('aset.view'), 403);

        $user = auth()->user();
        $activeCabangId = session('active_cabang_id');

        $assets = $this->buildFilteredQuery($request)->paginate(20)->withQueryString();

        // Stats
        $statsQuery = Asset::query();
        if (!$user->canAccessAllBranches()) {
            $cabangId = $activeCabangId ?? $user->defaultCabangId();
            $statsQuery->where('lokasi_id', $cabangId);
        } elseif ($activeCabangId) {
            $statsQuery->where('lokasi_id', $activeCabangId);
        }

        $totalAktif        = (clone $statsQuery)->where('status', StatusAset::Aktif)->count();
        $totalNilaiBuku    = (clone $statsQuery)->sum('nilai_buku');
        $totalPenyusutan   = AssetDepreciation::whereIn(
            'asset_id',
            (clone $statsQuery)->pluck('id')
        )->where('periode', date('Y-m'))->sum('jumlah_penyusutan');

        $kategoris = AssetCategory::orderBy('nama_kategori')->get();
        $kondisis  = KondisiAset::cases();
        $statuses  = StatusAset::cases();
        $lokasis   = Cabang::aktif()->orderBy('nama_cabang')->get();

        return view('aset.index', compact(
            'assets', 'kategoris', 'kondisis', 'statuses', 'lokasis',
            'totalAktif', 'totalNilaiBuku', 'totalPenyusutan'
        ));
    }

    /**
     * Export Excel — reuse buildFilteredQuery() yang SAMA dengan index(),
     * jadi export SELALU ikut filter (lokasi/kategori/kondisi/status/search)
     * yang sedang aktif, bukan seluruh data.
     */
    public function export(Request $request)
    {
        abort_unless(auth()->user()->can('aset.view'), 403);

        $filename = 'daftar-aset_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new AsetExport($this->buildFilteredQuery($request)), $filename);
    }

    /**
     * Form tambah aset baru
     */
    public function create()
    {
        abort_unless(auth()->user()->can('aset.manage'), 403);

        $kategoris     = AssetCategory::orderBy('nama_kategori')->get();
        $lokasis       = Cabang::aktif()->orderBy('nama_cabang')->get();
        $kondisis      = KondisiAset::cases();
        $metodes       = MetodePenyusutan::cases();
        $kodeAset      = $this->service->generateNomorAset();

        return view('aset.create', compact('kategoris', 'lokasis', 'kondisis', 'metodes', 'kodeAset'));
    }

    /**
     * Simpan aset baru
     */
    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('aset.manage'), 403);

        $request->merge([
            'harga_perolehan' => preg_replace('/\D/', '', $request->harga_perolehan ?? '0'),
            'nilai_residu'    => preg_replace('/\D/', '', $request->nilai_residu ?? '0'),
        ]);

        $request->validate([
            'kode_aset'                 => 'required|string|max:50|unique:assets,kode_aset',
            'nama_aset'                 => 'required|string|max:200',
            'kategori_aset_id'          => 'required|exists:asset_categories,id',
            'lokasi_id'                 => 'required|exists:cabangs,id',
            'tanggal_perolehan'         => 'required|date',
            'harga_perolehan'           => 'required|numeric|min:0',
            'nilai_residu'              => 'required|numeric|min:0',
            'umur_ekonomis_bulan'       => 'required|integer|min:1',
            'metode_penyusutan'         => 'required|in:' . implode(',', array_column(MetodePenyusutan::cases(), 'value')),
            'tarif_penyusutan'          => 'nullable|numeric|min:0|max:100',
            'estimasi_produksi_total'   => 'nullable|numeric|min:0',
            'kondisi'                   => 'required|in:' . implode(',', array_column(KondisiAset::cases(), 'value')),
            'foto'                      => 'nullable|image|max:2048',
            'catatan'                   => 'nullable|string',
        ]);

        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('assets', 'public');
        }

        $asset = Asset::create([
            'kode_aset'               => $request->kode_aset,
            'nama_aset'               => $request->nama_aset,
            'kategori_aset_id'        => $request->kategori_aset_id,
            'lokasi_id'               => $request->lokasi_id,
            'tanggal_perolehan'       => $request->tanggal_perolehan,
            'harga_perolehan'         => $request->harga_perolehan,
            'nilai_residu'            => $request->nilai_residu,
            'umur_ekonomis_bulan'     => $request->umur_ekonomis_bulan,
            'metode_penyusutan'       => $request->metode_penyusutan,
            'tarif_penyusutan'        => $request->tarif_penyusutan,
            'estimasi_produksi_total' => $request->estimasi_produksi_total,
            'kondisi'                 => $request->kondisi,
            'status'                  => StatusAset::Aktif,
            'nilai_buku'              => $request->harga_perolehan, // Belum ada penyusutan
            'foto'                    => $fotoPath,
            'catatan'                 => $request->catatan,
            'created_by'              => auth()->id(),
        ]);

        // Notifikasi ke Owner & Admin Pusat
        $recipients = NotificationService::getOwnerAndAdminPusat();
        NotificationService::send($recipients, new AsetNotification(
            $asset,
            'pembelian',
            'Nilai: Rp ' . number_format((float) $request->harga_perolehan, 0, ',', '.')
        ));

        return redirect()->route('aset.show', $asset)->with('success', 'Aset berhasil ditambahkan.');
    }

    /**
     * Detail aset
     */
    public function show(Asset $asset)
    {
        abort_unless(auth()->user()->can('aset.view'), 403);

        $asset->load(['kategori', 'lokasi', 'depreciations', 'maintenances', 'disposal', 'mutations.dariLokasi', 'mutations.keLokasi', 'createdBy']);
        $metodes = MetodePenyusutan::cases();

        return view('aset.show', compact('asset', 'metodes'));
    }

    /**
     * Form edit aset
     */
    public function edit(Asset $asset)
    {
        abort_unless(auth()->user()->can('aset.manage'), 403);

        $kategoris = AssetCategory::orderBy('nama_kategori')->get();
        $lokasis   = Cabang::aktif()->orderBy('nama_cabang')->get();
        $kondisis  = KondisiAset::cases();
        $metodes   = MetodePenyusutan::cases();
        $statuses  = StatusAset::cases();

        return view('aset.edit', compact('asset', 'kategoris', 'lokasis', 'kondisis', 'metodes', 'statuses'));
    }

    /**
     * Update aset
     */
    public function update(Request $request, Asset $asset)
    {
        abort_unless(auth()->user()->can('aset.manage'), 403);

        $request->merge([
            'harga_perolehan' => preg_replace('/\D/', '', $request->harga_perolehan ?? '0'),
            'nilai_residu'    => preg_replace('/\D/', '', $request->nilai_residu ?? '0'),
        ]);

        $request->validate([
            'kode_aset'               => 'required|string|max:50|unique:assets,kode_aset,' . $asset->id,
            'nama_aset'               => 'required|string|max:200',
            'kategori_aset_id'        => 'required|exists:asset_categories,id',
            'lokasi_id'               => 'required|exists:cabangs,id',
            'tanggal_perolehan'       => 'required|date',
            'harga_perolehan'         => 'required|numeric|min:0',
            'nilai_residu'            => 'required|numeric|min:0',
            'umur_ekonomis_bulan'     => 'required|integer|min:1',
            'metode_penyusutan'       => 'required|in:' . implode(',', array_column(MetodePenyusutan::cases(), 'value')),
            'tarif_penyusutan'        => 'nullable|numeric|min:0|max:100',
            'estimasi_produksi_total' => 'nullable|numeric|min:0',
            'kondisi'                 => 'required|in:' . implode(',', array_column(KondisiAset::cases(), 'value')),
            'status'                  => 'required|in:' . implode(',', array_column(StatusAset::cases(), 'value')),
            'catatan'                 => 'nullable|string',
        ]);

        $fotoPath = $asset->foto;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('assets', 'public');
        }

        $kondisiSebelum = $asset->kondisi?->value;

        // Simpan harga_perolehan/nilai_residu SEBELUM update untuk deteksi
        // perubahan (audit produksi menemukan: edit harga tanpa re-sync
        // nilai_buku bikin nilai_buku orphan/stale — lihat blok resync di bawah).
        $hargaPerolehanLama = (float) $asset->harga_perolehan;
        $nilaiResiduLama    = (float) $asset->nilai_residu;

        $asset->update([
            'kode_aset'               => $request->kode_aset,
            'nama_aset'               => $request->nama_aset,
            'kategori_aset_id'        => $request->kategori_aset_id,
            'lokasi_id'               => $request->lokasi_id,
            'tanggal_perolehan'       => $request->tanggal_perolehan,
            'harga_perolehan'         => $request->harga_perolehan,
            'nilai_residu'            => $request->nilai_residu,
            'umur_ekonomis_bulan'     => $request->umur_ekonomis_bulan,
            'metode_penyusutan'       => $request->metode_penyusutan,
            'tarif_penyusutan'        => $request->tarif_penyusutan,
            'estimasi_produksi_total' => $request->estimasi_produksi_total,
            'kondisi'                 => $request->kondisi,
            'status'                  => $request->status,
            'foto'                    => $fotoPath,
            'catatan'                 => $request->catatan,
        ]);

        // Sync nilai_buku kalau harga_perolehan/nilai_residu berubah — SATU-
        // SATUNYA titik dampak baru dari fix ini. Formula: nilai_buku_baru =
        // harga_perolehan_baru - SUM(depresiasi yang SUDAH tercatat), di-cap
        // minimum nilai_residu_baru (konsisten dgn floor di
        // AssetDepreciationService::hitungPenyusutanBulanan()). Kalau belum
        // ada depresiasi sama sekali, SUM=0 sehingga otomatis jadi
        // nilai_buku_baru = harga_perolehan_baru (setara reset penuh).
        $hargaBerubah  = abs((float) $asset->harga_perolehan - $hargaPerolehanLama) > 0.01;
        $residuBerubah = abs((float) $asset->nilai_residu - $nilaiResiduLama) > 0.01;
        if ($hargaBerubah || $residuBerubah) {
            $totalDepresiasi = (float) AssetDepreciation::where('asset_id', $asset->id)->sum('jumlah_penyusutan');
            $nilaiBukuBaru = max(
                (float) $asset->nilai_residu,
                (float) $asset->harga_perolehan - $totalDepresiasi
            );
            $asset->update(['nilai_buku' => $nilaiBukuBaru]);
        }

        // Notifikasi jika kondisi berubah jadi rusak
        $kondisiRusak = ['rusak_ringan', 'rusak_berat'];
        if (!in_array($kondisiSebelum, $kondisiRusak) && in_array($request->kondisi, $kondisiRusak)) {
            $recipients = NotificationService::getManagement($asset->lokasi_id);
            NotificationService::send($recipients, new AsetNotification(
                $asset,
                'rusak',
                'Kondisi: ' . $request->kondisi
            ));
        }

        $pesan = 'Data aset berhasil diperbarui.';
        if ($hargaBerubah || $residuBerubah) {
            $pesan .= ' Nilai buku otomatis disesuaikan menjadi Rp ' . number_format($asset->nilai_buku, 0, ',', '.') . '.';
        }

        return redirect()->route('aset.show', $asset)->with('success', $pesan);
    }

    /**
     * Hapus aset (hanya jika tidak aktif dan tidak punya riwayat data terkait)
     */
    public function destroy(Asset $asset, CascadeDeleteService $cascadeService)
    {
        abort_unless(auth()->user()->can('aset.manage'), 403);

        try {
            $nama = $asset->nama_aset;
            $cascadeService->deleteAssetCascade($asset);

            return redirect()->route('aset.index')
                ->with('success', "Aset <strong>{$nama}</strong> berhasil dihapus. Riwayat penyusutan & maintenance dipertahankan untuk audit.");
        } catch (\Exception $e) {
            return back()->with('error', "Gagal menghapus aset: " . $e->getMessage());
        }
    }

    /**
     * Hitung penyusutan untuk periode tertentu
     */
    public function hitungPenyusutan(Request $request, Asset $asset)
    {
        abort_unless(auth()->user()->can('aset.manage'), 403);

        $request->validate([
            'periode'          => 'required|date_format:Y-m',
            'produksi_aktual'  => 'nullable|numeric|min:0',
        ]);

        // generateAsetTertentu() idempotent: return null kalau sudah pernah
        // dihitung untuk periode ini ATAU nilai buku = nilai residu — sekaligus
        // otomatis catat TransaksiKeuangan non-cash (Beban Depresiasi).
        $existing = $asset->depreciations()->where('periode', $request->periode)->first();
        if ($existing) {
            return back()->with('error', 'Penyusutan untuk periode ' . $request->periode . ' sudah pernah dihitung.');
        }

        if ($asset->nilai_buku <= $asset->nilai_residu) {
            return back()->with('warning', 'Nilai buku aset sudah sama dengan nilai residu. Tidak ada penyusutan lagi.');
        }

        try {
            $dep = $this->service->generateAsetTertentu(
                $asset,
                $request->periode,
                (float) ($request->produksi_aktual ?? 0)
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Penyusutan berhasil dihitung. Nilai penyusutan: Rp ' . number_format($dep->jumlah_penyusutan, 0, ',', '.'));
    }

    /**
     * Trigger manual bulk: generate penyusutan bulan berjalan untuk SEMUA
     * aset aktif sekaligus (idempotent — aset yang sudah dihitung otomatis
     * dilewati). Pelengkap trigger per-aset (hitungPenyusutan) yang sudah ada,
     * bukan pengganti — keduanya tetap berfungsi berdampingan.
     */
    public function generateDepresiasiBulanIni()
    {
        abort_unless(auth()->user()->can('aset.depresiasi.auto'), 403);

        $hasil = $this->service->generateBulanIni();

        $pesan = "Selesai: {$hasil['berhasil']} aset berhasil, {$hasil['dilewati']} dilewati (sudah ada), {$hasil['gagal']} gagal.";

        return back()->with($hasil['gagal'] > 0 ? 'warning' : 'success', $pesan);
    }

    /**
     * Tambah catatan perawatan
     */
    public function tambahMaintenance(Request $request, Asset $asset)
    {
        abort_unless(auth()->user()->can('aset.manage'), 403);

        $request->validate([
            'tanggal_maintenance' => 'required|date',
            'jenis'               => 'required|in:perawatan_rutin,perbaikan,overhaul',
            'deskripsi'           => 'required|string',
            'biaya'               => 'nullable|numeric|min:0',
            'vendor_maintenance'  => 'nullable|string|max:200',
        ]);

        AssetMaintenance::create([
            'asset_id'            => $asset->id,
            'tanggal_maintenance' => $request->tanggal_maintenance,
            'jenis'               => $request->jenis,
            'deskripsi'           => $request->deskripsi,
            'biaya'               => $request->biaya ?? 0,
            'vendor_maintenance'  => $request->vendor_maintenance,
            'created_by'          => auth()->id(),
        ]);

        // Notifikasi ke Manajer Cabang & Owner
        $recipients = NotificationService::getManagement($asset->lokasi_id);
        NotificationService::send($recipients, new AsetNotification(
            $asset,
            'maintenance',
            ucfirst($request->jenis) . ': ' . $request->deskripsi
        ));

        return back()->with('success', 'Catatan perawatan berhasil ditambahkan.');
    }

    /**
     * Disposal aset (penjualan / pembuangan / hibah)
     */
    public function disposal(Request $request, Asset $asset)
    {
        abort_unless(auth()->user()->can('aset.manage'), 403);

        if ($asset->disposal) {
            return back()->with('error', 'Aset ini sudah pernah dilakukan disposal.');
        }

        $request->validate([
            'tanggal_disposal' => 'required|date',
            'tipe'             => 'required|in:dijual,dibuang,dihibahkan',
            'nilai_jual'       => 'nullable|numeric|min:0',
            'pembeli'          => 'nullable|string|max:200',
            'catatan'          => 'nullable|string',
        ]);

        $nilaiBuku = (float) $asset->nilai_buku;
        $nilaiJual = (float) ($request->nilai_jual ?? 0);
        $keuntunganKerugian = $nilaiJual - $nilaiBuku;

        AssetDisposal::create([
            'asset_id'              => $asset->id,
            'tanggal_disposal'      => $request->tanggal_disposal,
            'tipe'                  => $request->tipe,
            'nilai_jual'            => $nilaiJual,
            'nilai_buku_saat_disposal' => $nilaiBuku,
            'keuntungan_kerugian'   => $keuntunganKerugian,
            'pembeli'               => $request->pembeli,
            'catatan'               => $request->catatan,
            'created_by'            => auth()->id(),
        ]);

        // Update status aset
        $statusBaru = match($request->tipe) {
            'dijual'     => StatusAset::Dijual,
            'dibuang'    => StatusAset::Dihapuskan,
            'dihibahkan' => StatusAset::Dihapuskan,
            default      => StatusAset::Dihapuskan,
        };

        $asset->update(['status' => $statusBaru]);

        return back()->with('success', 'Disposal aset berhasil dicatat.');
    }
}
