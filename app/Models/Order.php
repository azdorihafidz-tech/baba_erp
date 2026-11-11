<?php

namespace App\Models;

use App\Enums\StatusOrder;
use App\Enums\StatusProduksi;
use App\Enums\TipeOrder;
use App\Enums\TipePembayaran;
use App\Enums\TipeTransaksi;
use App\Traits\DijagaClosing;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasCabang, HasAuditLog, SoftDeletes, FillsDeletedBy, DijagaClosing;

    protected $fillable = [
        'cabang_id',
        'nomor_order',
        'tanggal_order',
        'tipe_order',
        // Tahap 3 D'mentai — POS
        'tipe_transaksi',
        'nomor_meja',
        'tanggal_expired_frozen',
        'pelanggan_id',
        'nama_pelanggan',
        'telepon_pelanggan',
        'total_harga',
        'diskon',
        'service_charge',
        'take_away_fee',
        'total_bayar',
        'jumlah_bayar',
        'kembalian',
        'tipe_pembayaran',
        'kas_id',
        'status',
        'catatan',
        'kasir_id',
        'bukti_pembayaran',
        // Antrian produksi
        'nomor_antrian',
        'berat_daging_kg',
        'status_produksi',
        'tampil_di_antrian',
        'lokasi_rak',
        'dikerjakan_oleh_id',
        'waktu_mulai_kerja',
        'waktu_selesai_kerja',
        'waktu_disimpan',
        'waktu_diambil',
        'catatan_produksi',
        // Order pengganti & pembatalan
        'parent_order_id',
        'alasan_pembatalan_kategori',
        'alasan_pembatalan_detail',
        // Tahap C 4.38 — nota POS gudang yang memproses PO cabang
        'purchase_order_id',
        // 4.49 Tahap B2 — Kirim ke Cabang (porting erp-coffeeshop)
        'customer_type',
        'outlet_tujuan_id',
        'status_kirim',
        'diterima_at',
        'diterima_by',
        'alasan_tolak',
        // 4.49 Tahap B3 — order koreksi retur (nilai minus) dibuat sistem
        'retur_internal_id',
    ];

    // ===== Kunci Closing (DijagaClosing) =====
    // Hanya kolom yang mempengaruhi angka closing yang dijaga — status
    // antrian/produksi, catatan, dll tetap bebas diubah.
    protected function closingKolomTanggal(): string
    {
        return 'tanggal_order';
    }

    protected function closingKonteks(): string
    {
        return 'Order';
    }

    protected function closingKolomDijaga(): ?array
    {
        return [
            'cabang_id', 'tanggal_order', 'status', 'total_harga', 'diskon', 'service_charge',
            'take_away_fee', 'total_bayar', 'tipe_pembayaran', 'kas_id', 'tipe_transaksi',
        ];
    }

    /**
     * 4.49 — order yang SEBELUMNYA tidak dihitung laporan (kiriman menunggu
     * diterima, bill tersimpan, dibatalkan) tidak mengubah angka hari lamanya,
     * jadi tanggal lama tidak perlu dicek kuncinya saat tanggal dipindah
     * (kiriman diterima di tanggal Terima). Tanggal baru tetap dicek.
     */
    protected function closingAbaikanTanggalLama(): bool
    {
        $lama = $this->getOriginal('status');
        $lama = $lama instanceof StatusOrder ? $lama->value : (string) $lama;

        return in_array($lama, StatusOrder::bukanPenjualan(), true);
    }

    protected function casts(): array
    {
        return [
            'status'              => StatusOrder::class,
            'tipe_order'          => TipeOrder::class,
            'tipe_transaksi'      => TipeTransaksi::class,
            'tipe_pembayaran'     => TipePembayaran::class,
            'status_produksi'     => StatusProduksi::class,
            'tanggal_order'       => 'date',
            'tanggal_expired_frozen' => 'date',
            'total_harga'         => 'decimal:2',
            'diskon'              => 'decimal:2',
            'service_charge'      => 'decimal:2',
            'take_away_fee'       => 'decimal:2',
            'total_bayar'         => 'decimal:2',
            'jumlah_bayar'        => 'decimal:2',
            'kembalian'           => 'decimal:2',
            'berat_daging_kg'     => 'decimal:2',
            'tampil_di_antrian'   => 'boolean',
            'waktu_mulai_kerja'   => 'datetime',
            'waktu_selesai_kerja' => 'datetime',
            'waktu_disimpan'      => 'datetime',
            'waktu_diambil'       => 'datetime',
            'diterima_at'         => 'datetime',
        ];
    }

    /**
     * Penjualan sah untuk laporan: belum dihapus, bukan bill belum dibayar,
     * bukan dibatalkan. deleted_at ditulis eksplisit karena banyak laporan
     * memakai withoutGlobalScopes() (yang ikut mematikan SoftDeletes).
     */
    public function scopePenjualanSah($query)
    {
        return $query->whereNull($query->qualifyColumn('deleted_at'))
            ->whereNotIn($query->qualifyColumn('status'), StatusOrder::bukanPenjualan());
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    // ===== 4.49 Tahap B2 — Kirim ke Cabang =====

    /** Cabang tujuan kiriman. */
    public function outletTujuan()
    {
        return $this->belongsTo(Cabang::class, 'outlet_tujuan_id');
    }

    /** Rincian barang yang keluar dari stok gudang / masuk ke cabang tujuan. */
    public function kirimanItems()
    {
        return $this->hasMany(OrderKirimanItem::class);
    }

    public function diterimaOleh()
    {
        return $this->belongsTo(User::class, 'diterima_by');
    }

    /** 4.49 Tahap B3 — order koreksi retur (nilai minus), dibuat sistem: tidak bisa dibatalkan/diedit/dihapus. */
    public function returInternal()
    {
        return $this->belongsTo(ReturInternal::class);
    }

    public function isKoreksiRetur(): bool
    {
        return $this->retur_internal_id !== null;
    }

    /**
     * 4.49 — kolom kiriman (migration 2026_11_10_300001) sudah ada? Laporan memeriksa ini
     * supaya tetap jalan di jeda antara program baru terpasang dan migration dijalankan.
     */
    public static function adaKolomKiriman(): bool
    {
        static $ada = null;

        return $ada ??= \Illuminate\Support\Facades\Schema::hasColumn('orders', 'customer_type');
    }

    /** Penjualan Gudang ke cabang sendiri lewat alur Kirim → Terima. */
    public function isKirimanCabang(): bool
    {
        return $this->customer_type === 'internal' && $this->status_kirim !== null && $this->retur_internal_id === null;
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function kas()
    {
        return $this->belongsTo(Kas::class);
    }

    /** Tahap 3 D'mentai — Split Payment: rincian metode bayar order ini. */
    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function dikerjakanOleh()
    {
        return $this->belongsTo(Karyawan::class, 'dikerjakan_oleh_id');
    }

    /** Tahap C 4.38 — PO cabang yang diproses lewat nota POS ini. */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** Order asli yang digantikan oleh order ini (kalau order ini pengganti) */
    public function parentOrder()
    {
        return $this->belongsTo(Order::class, 'parent_order_id');
    }

    /** Order pengganti yang dibuat untuk menggantikan order ini */
    public function childOrders()
    {
        return $this->hasMany(Order::class, 'parent_order_id');
    }

    /** Generate nomor antrian berikutnya untuk cabang & tanggal tertentu */
    public static function generateNomorAntrian(int $cabangId, $tanggal): int
    {
        $max = self::withTrashed()
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal_order', $tanggal)
            ->whereNotNull('nomor_antrian')
            ->max('nomor_antrian');

        return ($max ?? 0) + 1;
    }

    public function mulaiKerja(int $karyawanId): void
    {
        $this->update([
            'status_produksi'   => StatusProduksi::Dikerjakan,
            'dikerjakan_oleh_id'=> $karyawanId,
            'waktu_mulai_kerja' => now(),
        ]);
    }

    public function tandaiSelesai(): void
    {
        $this->update([
            'status_produksi'     => StatusProduksi::Selesai,
            'waktu_selesai_kerja' => now(),
        ]);
    }

    public function simpanDiRak(string $lokasi = ''): void
    {
        $this->update([
            'status_produksi' => StatusProduksi::Disimpan,
            'lokasi_rak'      => $lokasi,
            'waktu_disimpan'  => now(),
        ]);
    }

    public function tandaiDiambil(): void
    {
        $this->update([
            'status_produksi' => StatusProduksi::Diambil,
            'waktu_diambil'   => now(),
        ]);
    }
}
