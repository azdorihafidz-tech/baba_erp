<?php

namespace App\Services;

use App\Enums\StatusAset;
use App\Models\Asset;
use App\Models\Cabang;
use App\Models\Kas;
use App\Models\NeracaSetting;
use App\Models\PurchaseOrder;
use App\Models\StockBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan Neraca (Balance Sheet) formal berstandar SAK ETAP — murni READ dari
 * data existing (Kas, Stock Batches FIFO, Asset, Purchase Order, Chart of
 * Accounts). Tidak menyentuh/menulis data manapun.
 *
 * CATATAN PENTING soal parameter $tanggal: Kas (saldo_sekarang), Persediaan
 * (stock_batches.qty_sisa), dan Nilai Buku Aset SELALU mencerminkan kondisi
 * TERKINI (sistem ini tidak menyimpan snapshot historis harian untuk
 * ketiganya) — cuma "Laba Ditahan" yang benar-benar menghormati cutoff
 * tanggal (dihitung dari transaksi s/d tanggal tersebut). Disclaimer ini
 * WAJIB ditampilkan di UI, bukan disembunyikan.
 *
 * CATATAN PENTING soal balance_check: Neraca WAJAR TIDAK BALANCE persis untuk
 * data historis yang direkam SEBELUM sistem ini formal double-entry (mis.
 * aset yang didata langsung ke tabel `assets` tanpa transaksi Kas pembelian
 * yang match persis, atau modal awal yang masuk lewat jalur Transfer bukan
 * kategori Modal). Ini BUKAN bug — `modal_owner` di `NeracaSetting` sengaja
 * dibuat sebagai figure yang bisa di-adjust manual oleh Owner supaya Neraca
 * bisa di-"true up" seiring waktu, alih-alih sistem memaksakan rekonsiliasi
 * otomatis atas data historis yang tidak lengkap.
 */
class NeracaService
{
    public function __construct(private LabaRugiFormalService $labaRugiService)
    {
    }

    public function hitungNeraca(Carbon $tanggal, ?int $cabangId = null): array
    {
        $cabang = $cabangId ? Cabang::find($cabangId) : null;

        $aset = $this->hitungAset($cabangId);
        $kewajiban = $this->hitungKewajiban($cabangId);
        $modal = $this->hitungModal($tanggal, $cabangId);

        $totalKewajibanModal = $kewajiban['total_kewajiban'] + $modal['total_modal'];
        $selisih = round($aset['total_aset'] - $totalKewajibanModal, 2);

        return [
            'tanggal' => $tanggal->toDateString(),
            'cabang' => $cabang,
            'aset' => $aset,
            'kewajiban' => $kewajiban,
            'modal' => $modal,
            'total_kewajiban_modal' => $totalKewajibanModal,
            'balance_check' => abs($selisih) < 1,
            'selisih' => $selisih,
        ];
    }

    private function hitungAset(?int $cabangId): array
    {
        // Aset Lancar — Kas & Setara Kas
        $kasQuery = Kas::where('is_active', true);
        if ($cabangId) {
            $kasQuery->where('cabang_id', $cabangId);
        }
        $kasSetara = (float) $kasQuery->sum('saldo_sekarang');

        // Aset Lancar — Persediaan (FIFO stock_batches, qty_sisa masih ada)
        $persediaanQuery = StockBatch::join('items', 'items.id', '=', 'stock_batches.item_id')
            ->where('stock_batches.qty_sisa', '>', 0)
            ->whereNull('stock_batches.deleted_at');
        if ($cabangId) {
            $persediaanQuery->where('stock_batches.lokasi_id', $cabangId);
        }
        $persediaanPerTipe = (clone $persediaanQuery)
            ->selectRaw('items.tipe, SUM(stock_batches.qty_sisa * stock_batches.harga_beli_per_unit) as nilai')
            ->groupBy('items.tipe')
            ->pluck('nilai', 'tipe');

        // "lainnya"/"tambahan_gratis" (item tanpa tipe jelas / add-on gratis)
        // digabung ke bucket bahan baku — COA cuma punya 3 leaf persediaan
        // (bahan baku/barang jadi/kemasan), tidak ada akun "persediaan
        // lainnya" tersendiri. Tahap 2.5 D'mentai: 'produk_jadi' pecah jadi
        // 'produk_jual'+'produk_tambahan', keduanya tetap masuk bucket
        // Barang Jadi (1:1 rename, sengaja tidak menambah bucket baru).
        $persediaanBahanBaku = (float) ($persediaanPerTipe['bahan_baku'] ?? 0)
            + (float) ($persediaanPerTipe['lainnya'] ?? 0)
            + (float) ($persediaanPerTipe['tambahan_gratis'] ?? 0);
        $persediaanBarangJadi = (float) ($persediaanPerTipe['produk_jual'] ?? 0)
            + (float) ($persediaanPerTipe['produk_tambahan'] ?? 0);
        $persediaanKemasan = (float) ($persediaanPerTipe['kemasan'] ?? 0);
        $totalPersediaan = $persediaanBahanBaku + $persediaanBarangJadi + $persediaanKemasan;

        $piutang = 0.0; // Tidak ada fitur pencatatan piutang usaha saat ini

        $totalAsetLancar = $kasSetara + $piutang + $totalPersediaan;

        // Aset Tetap
        $assetQuery = Asset::where('status', StatusAset::Aktif);
        if ($cabangId) {
            $assetQuery->where('lokasi_id', $cabangId);
        }
        $assetList = $assetQuery->get(['id', 'nama_aset', 'harga_perolehan', 'nilai_buku']);

        $asetBruto = (float) $assetList->sum('harga_perolehan');
        $nilaiBukuAset = (float) $assetList->sum('nilai_buku');
        $akumulasiDepresiasi = $asetBruto - $nilaiBukuAset;

        $detailAset = $assetList->map(fn ($a) => [
            'nama' => $a->nama_aset,
            'harga_perolehan' => (float) $a->harga_perolehan,
            'akum_depresiasi' => (float) $a->harga_perolehan - (float) $a->nilai_buku,
            'nilai_buku' => (float) $a->nilai_buku,
        ])->values()->toArray();

        return [
            'lancar' => [
                'kas_setara' => $kasSetara,
                'piutang' => $piutang,
                'persediaan_bahan_baku' => $persediaanBahanBaku,
                'persediaan_barang_jadi' => $persediaanBarangJadi,
                'persediaan_kemasan' => $persediaanKemasan,
                'total' => $totalAsetLancar,
            ],
            'tetap' => [
                'aset_bruto' => $asetBruto,
                'akumulasi_depresiasi' => $akumulasiDepresiasi,
                'nilai_buku' => $nilaiBukuAset,
                'detail' => $detailAset,
            ],
            'total_aset' => $totalAsetLancar + $nilaiBukuAset,
        ];
    }

    private function hitungKewajiban(?int $cabangId): array
    {
        // Hutang Usaha — PO diterima tapi belum ada TransaksiKeuangan referensi
        $hutangQuery = PurchaseOrder::where('status', 'diterima')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('transaksi_keuangans')
                    ->whereColumn('transaksi_keuangans.referensi_id', 'purchase_orders.id')
                    ->where('transaksi_keuangans.referensi_type', 'purchase_order')
                    ->whereNull('transaksi_keuangans.deleted_at');
            });
        if ($cabangId) {
            $hutangQuery->where('cabang_id', $cabangId);
        }
        $hutangUsaha = (float) $hutangQuery->sum('total_harga');

        return [
            'jangka_pendek' => [
                'hutang_usaha' => $hutangUsaha,
                'hutang_pajak' => 0.0, // tidak ada fitur pencatatan hutang pajak saat ini
                'total' => $hutangUsaha,
            ],
            'jangka_panjang' => [
                'hutang_bank' => 0.0, // tidak ada fitur pencatatan hutang bank saat ini
                'total' => 0.0,
            ],
            'total_kewajiban' => $hutangUsaha,
        ];
    }

    private function hitungModal(Carbon $tanggal, ?int $cabangId): array
    {
        $modalOwner = (float) NeracaSetting::getSetting()->modal_owner;

        // Laba Ditahan = akumulasi laba bersih SEMUA transaksi historis s/d
        // tanggal cutoff (bukan cuma 1 periode) — pakai LabaRugiFormalService
        // dengan tanggal_mulai sangat awal supaya benar2 kumulatif sejak awal.
        $labaRugi = $this->labaRugiService->hitungLabaRugi(
            Carbon::create(2000, 1, 1),
            $tanggal->copy()->endOfDay(),
            $cabangId
        );
        $labaDitahan = (float) $labaRugi['laba_bersih_setelah_pajak'];

        return [
            'modal_owner' => $modalOwner,
            'laba_ditahan' => $labaDitahan,
            'total_modal' => $modalOwner + $labaDitahan,
        ];
    }
}
