<?php

namespace App\Models;

use App\Enums\JenisItem;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'kode_item', 'barcode', 'nama_item', 'item_category_id', 'tipe', 'satuan',
        'harga_jual', 'harga_beli_terakhir', 'qty_minimum', 'is_active',
        'jenis', 'track_stok', 'punya_varian', 'stok_per_varian', 'foto',
    ];

    protected $fillable = [
        'cabang_id',
        'kode_item',
        'barcode', // BABA Tahap 6a — scan di kasir
        'nama_item',
        'item_category_id',
        'tipe',
        'satuan',
        'harga_jual',
        'harga_beli_terakhir', // = Harga Standar (manual), lihat CLAUDE.md 4.38
        'harga_po_terakhir',
        'harga_po_terakhir_at',
        'qty_minimum',
        'deskripsi',
        'is_active',
        // Fase 5 — Modul Perlengkapan (Rule #66), murni additive: item
        // existing tetap jenis='bahan_baku'/track_stok=true (default kolom).
        'jenis',
        'track_stok',
        // Tahap 2 D'mentai — Fitur Varian (DB+Model saja, UI di Tahap 3).
        'punya_varian',
        'stok_per_varian',
        'foto',
        // Sprint Unit Family (2026-09-27): unit_beli + isi_per_unit_beli.
        // Kalau di-isi, form PO/Adjustment menampilkan dropdown Unit dan
        // auto-convert qty & harga ke unit pakai (satuan) sebelum simpan.
        'unit_beli',
        'isi_per_unit_beli',
    ];

    protected function casts(): array
    {
        return [
            'harga_jual' => 'decimal:2',
            'harga_beli_terakhir' => 'decimal:2',
            'harga_po_terakhir' => 'decimal:2',
            'harga_po_terakhir_at' => 'datetime',
            'qty_minimum' => 'decimal:3',
            'is_active' => 'boolean',
            'jenis' => JenisItem::class,
            'track_stok' => 'boolean',
            'punya_varian' => 'boolean',
            'stok_per_varian' => 'boolean',
            'isi_per_unit_beli' => 'decimal:3',
        ];
    }

    /** Sprint Unit Family (2026-09-27) — barang ini bisa di-input dgn unit beli? */
    public function hasUnitBeli(): bool
    {
        return !empty($this->unit_beli) && (float) $this->isi_per_unit_beli > 0;
    }

    /**
     * Konversi qty dari $unit (unit_beli atau satuan) ke unit pakai (satuan).
     * $unit yang tidak dikenal dianggap unit pakai (return apa adanya) —
     * fail-safe supaya data lama (unit_input NULL) tidak error.
     */
    public function convertToUnitPakai(float $qty, ?string $unit = null): float
    {
        if ($unit !== null && $this->hasUnitBeli() && strcasecmp($unit, (string) $this->unit_beli) === 0) {
            return $qty * (float) $this->isi_per_unit_beli;
        }
        return $qty;
    }

    /** Nama + cabang pemilik (hanya saat mode "Semua Cabang"), utk dropdown pilih barang (4.39). */
    public function getNamaDenganCabangAttribute(): string
    {
        $nama = $this->nama_item;
        if ($this->cabang_id && ! \App\Support\CabangAktif::id()) {
            $nama .= ' — ' . ($this->cabang?->nama_cabang ?? '#' . $this->cabang_id);
        }

        return $nama;
    }

    /** Pemilik cabang barang (CLAUDE.md 4.39 — data terpisah per cabang). */
    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    /** Barang milik 1 cabang. null = semua cabang (mode "Semua Cabang"). */
    public function scopeMilikCabang($query, ?int $cabangId)
    {
        // cabang_id NULL = data lama belum dipecah → tampil di semua cabang (pengaman)
        return $cabangId
            ? $query->where(fn ($q) => $q->where($this->getTable() . '.cabang_id', $cabangId)->orWhereNull($this->getTable() . '.cabang_id'))
            : $query;
    }

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    // ===== Tahap 2 D'mentai — Fitur Varian (DB+Model saja, UI di Tahap 3) =====

    public function attributes()
    {
        return $this->hasMany(ItemAttribute::class)->orderBy('urutan');
    }

    public function variants()
    {
        return $this->hasMany(ItemVariant::class)->orderBy('urutan');
    }

    /**
     * Tahap 3 D'mentai — resep produksi item ini (nullable, item tanpa
     * breakdown bahan baku seperti minuman kemasan jadi/Item Tambahan tidak
     * wajib punya resep). Dipakai POS untuk cek+potong stok otomatis saat
     * checkout tanpa kasir pilih manual.
     */
    public function resep()
    {
        return $this->hasOne(ResepBumbu::class);
    }

    /** 4.49 Tahap B1 — Resep Produksi bahan setengah jadi (menu Produksi) — BUKAN komposisi POS. */
    public function resepProduksi()
    {
        return $this->hasOne(ResepProduksi::class);
    }

    /**
     * Tahap 2.5 D'mentai — ketersediaan produk_jual/produk_tambahan per
     * outlet. Config eksplisit di `item_cabang` (create/edit form Produk
     * Jual). Lihat migration `create_item_cabang_table` untuk alasan
     * fallback ini.
     */
    public function itemCabang()
    {
        return $this->hasMany(ItemCabang::class);
    }

    /**
     * Item TANPA row sama sekali = dianggap aktif di semua cabang (backward
     * compat utk item lama yang belum pernah di-assign eksplisit). Row
     * eksplisit is_active=false = sengaja di-exclude dari outlet itu.
     */
    public function tersediaDiCabang(int $cabangId): bool
    {
        // 4.39 — barang milik cabang: tersedia hanya di cabangnya sendiri
        if ($this->cabang_id !== null) {
            return (int) $this->cabang_id === $cabangId && (bool) $this->is_active;
        }
        $row = $this->itemCabang->firstWhere('cabang_id', $cabangId);
        return $row === null || $row->is_active;
    }

    /** Harga jual efektif di 1 cabang — pakai override kalau ada & aktif, else harga_jual default. */
    public function hargaEfektifDiCabang(int $cabangId): float
    {
        // 4.39 — barang milik cabang: harga = harga jualnya sendiri (item_cabang hanya utk data lama)
        if ($this->cabang_id !== null) {
            return (float) ($this->harga_jual ?? 0);
        }
        $row = $this->itemCabang->firstWhere('cabang_id', $cabangId);
        if ($row && $row->is_active && $row->harga_override !== null) {
            return (float) $row->harga_override;
        }
        return (float) ($this->harga_jual ?? 0);
    }

    /**
     * Harga BELI bahan/kemasan di 1 cabang (harga awal PO cabang itu):
     * item_cabang.harga_beli kalau diisi, else Harga Standar
     * (`harga_beli_terakhir`, manual — tidak lagi ditimpa PO, CLAUDE.md 4.38).
     */
    public function hargaBeliDiCabang(?int $cabangId): float
    {
        if ($cabangId && $this->cabang_id === null) { // 4.39 — barang milik cabang pakai Harga Standar-nya sendiri
            $row = $this->relationLoaded('itemCabang')
                ? $this->itemCabang->firstWhere('cabang_id', $cabangId)
                : $this->itemCabang()->where('cabang_id', $cabangId)->first();
            if ($row && $row->harga_beli !== null) {
                return (float) $row->harga_beli;
            }
        }

        return (float) ($this->harga_beli_terakhir ?? 0);
    }

    /**
     * Tahap 3 D'mentai — cek cepat "bisa dijual minimal 1 unit di cabang ini
     * sekarang?" dipakai grid POS untuk grey-out produk yang bahan bakunya
     * habis. Item dengan resep: SEMUA bahan komposisi harus tersedia
     * (>0). Tanpa resep: stok item itu sendiri yang dicek.
     *
     * Tahap 2.5: whitelist tipe yang dipotong stok langsung disamakan
     * dengan `produk_jual` (dulu `produk_jadi`) — 1:1 rename, sengaja
     * TIDAK menambah tambahan_gratis/produk_tambahan (behaviornya
     * dipertahankan sama seperti nilai lama 'lainnya': selalu sellable
     * tanpa cek stok, sesuai perilaku sebelum restructure ini).
     */
    public function bisaDijualDiCabang(int $cabangId): bool
    {
        // 4.40 — rumus sama dgn POS & pengecekan saat Bayar (termasuk bahan di dalam Bumbu Pusat)
        return app(\App\Services\KetersediaanProdukService::class)->untuk(collect([$this]), $cabangId)[$this->id]['bisa_dijual'];
    }

    public function stokDiLokasi(int $lokasiId): float
    {
        return $this->stocks()->where('lokasi_id', $lokasiId)->value('qty') ?? 0;
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBahanBaku($query)
    {
        return $query->where('tipe', 'bahan_baku');
    }

    public function scopeProdukJual($query)
    {
        return $query->where('tipe', 'produk_jual');
    }

    /**
     * Sprint 4.33 (2026-10-08) — tipe barang yang GENUINELY DIBELI dari supplier
     * (dipakai filter dropdown PO, Stock Transfer, Stock Request).
     *
     * `produk_jual` + `produk_tambahan` SENGAJA tidak masuk — keduanya dibuat
     * sendiri di dapur lewat resep (CLAUDE.md [[4.30]]), bukan dibeli.
     * `tambahan_gratis` (sumpit/garpu/saus kecil) MASUK — ini genuinely dibeli
     * dari supplier walau di POS gratis utk customer.
     * Legacy `produk_jadi`/`lainnya` tidak dimasukkan (sudah tidak dipakai
     * data baru sejak Tahap 2.5 rename, lihat CLAUDE.md 8.3).
     */
    public const TIPE_DAPAT_DIBELI = ['bahan_baku', 'kemasan', 'tambahan_gratis'];

    /** Tahap C 4.38 — produk yang boleh dipesan cabang lewat PO ke supplier INTERNAL (lokasi kita). */
    public const TIPE_PRODUK_INTERNAL = ['produk_jual', 'produk_tambahan'];

    public function scopeDapatDibeli($query)
    {
        return $query->whereIn('tipe', self::TIPE_DAPAT_DIBELI);
    }

    // ===== Fase 5 — Modul Perlengkapan (Rule #66), scope BARU, tidak
    // menyentuh scope existing di atas sama sekali =====

    public function scopeJenis($query, string $jenis)
    {
        return $query->where('jenis', $jenis);
    }

    public function scopePerlengkapan($query)
    {
        return $query->where('jenis', JenisItem::Perlengkapan->value);
    }
}
