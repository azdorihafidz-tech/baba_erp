<?php

namespace App\Models;

use App\Enums\TipeCabang;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cabang extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'nama_cabang', 'kode_cabang', 'alamat', 'tipe',
        'kepala_cabang_id', 'latitude', 'longitude',
        'radius_absen_meter', 'jam_masuk', 'is_active',
        'antrian_produksi_aktif', 'running_text_aktif', 'footer_struk',
        'izinkan_sembunyi_harga_struk',
        'label_cup_aktif', 'label_cup_kategori', 'label_cup_lebar_mm', 'label_cup_tinggi_mm',
        'label_cup_geser_x', 'label_cup_geser_y',
    ];

    /** BABA Tahap 3 — jenis toko (Pemegang Stok). Kemitraan & toko member: hanya laporan transaksi, produk, kategori. */
    public const JENIS_TOKO = ['baba' => 'Toko BABA', 'kemitraan' => 'Toko Kemitraan', 'toko_member' => 'Toko Refill Member'];

    protected $fillable = [
        'nama_cabang',
        'kode_cabang',
        'alamat',
        'telepon',
        'kepala_cabang_id',
        'tipe',
        'jenis_toko', 'member_id', // BABA Tahap 3
        'is_active',
        'latitude',
        'longitude',
        'radius_absen_meter',
        'jam_masuk',
        'antrian_produksi_aktif',
        'running_text',
        'running_text_aktif',
        'footer_struk',
        'izinkan_sembunyi_harga_struk',
        // Tahap 3 D'mentai — config POS per outlet (CLAUDE.md 1.4: jangan hardcode)
        'dine_in_aktif',
        'takeaway_aktif',
        'frozen_aktif',
        'nomor_meja_aktif',
        // 4.41 — pilihan tipe transaksi di POS bisa disembunyikan
        'pilih_tipe_transaksi',
        'tipe_transaksi_default',
        'take_away_fee',
        'service_charge_persen',
        // Tahap B 4.38 — wajib bukti bayar non-tunai per cabang
        'wajib_bukti_transfer',
        'wajib_bukti_qris',
        'kirim_cabang_aktif', // 4.49 — saklar Kirim ke Cabang di POS Gudang (bawaan mati)
        // 4.44 Poin 2 — Label Produk (stiker) per cabang, nama kolom = coffeeshop "label cup"
        'label_cup_aktif',
        'label_cup_kategori',
        'label_cup_lebar_mm',
        'label_cup_tinggi_mm',
        'label_cup_geser_x', // 4.49 Tahap D — geser posisi label (mm)
        'label_cup_geser_y',
    ];

    protected function casts(): array
    {
        return [
            'tipe'                   => TipeCabang::class,
            'is_active'              => 'boolean',
            'latitude'               => 'decimal:8',
            'longitude'              => 'decimal:8',
            'radius_absen_meter'     => 'integer',
            'antrian_produksi_aktif' => 'boolean',
            'running_text_aktif'     => 'boolean',
            'izinkan_sembunyi_harga_struk' => 'boolean',
            'dine_in_aktif'          => 'boolean',
            'takeaway_aktif'         => 'boolean',
            'frozen_aktif'           => 'boolean',
            'wajib_bukti_transfer'   => 'boolean',
            'wajib_bukti_qris'       => 'boolean',
            'kirim_cabang_aktif'     => 'boolean',
            'nomor_meja_aktif'       => 'boolean',
            'pilih_tipe_transaksi'   => 'boolean',
            'take_away_fee'          => 'decimal:2',
            'service_charge_persen'  => 'decimal:2',
            'label_cup_aktif'        => 'boolean',
            'label_cup_kategori'     => 'array',
            'label_cup_lebar_mm'     => 'integer',
            'label_cup_tinggi_mm'    => 'integer',
            'label_cup_geser_x'      => 'float',
            'label_cup_geser_y'      => 'float',
        ];
    }

    /**
     * 4.44 Poin 2 — kode kategori yang dicetak Label Produk. Belum pernah
     * diatur (NULL) = semua kategori yang punya produk jual.
     */
    public function kategoriLabelCup(): array
    {
        if (is_array($this->label_cup_kategori)) {
            return array_values($this->label_cup_kategori);
        }

        return \App\Models\ItemCategory::whereHas('items', fn ($q) => $q->where('tipe', 'produk_jual'))
            ->pluck('kode_kategori')->filter()->values()->all();
    }

    /** Tahap 3 D'mentai — cek apakah tipe transaksi ini aktif utk cabang ini. */
    public function tipeTransaksiAktif(\App\Enums\TipeTransaksi $tipe): bool
    {
        return (bool) $this->{$tipe->kolomAktif()};
    }

    /** Tipe transaksi yang aktif di cabang ini (urutan enum). */
    public function daftarTipeTransaksiAktif(): \Illuminate\Support\Collection
    {
        return collect(\App\Enums\TipeTransaksi::cases())->filter(fn ($t) => $this->tipeTransaksiAktif($t))->values();
    }

    /**
     * 4.41 — tipe yang otomatis dipakai POS tanpa kasir memilih:
     * pilihan disembunyikan → tipe bawaan cabang; hanya 1 tipe aktif → tipe itu;
     * selain itu null (kasir memilih sendiri).
     */
    public function tipeTransaksiOtomatis(): ?string
    {
        if (($this->pilih_tipe_transaksi ?? true) === false) {
            return \App\Enums\TipeTransaksi::tryFrom((string) $this->tipe_transaksi_default)?->value ?? \App\Enums\TipeTransaksi::Takeaway->value;
        }
        $aktif = $this->daftarTipeTransaksiAktif();

        return $aktif->count() === 1 ? $aktif->first()->value : null;
    }

    public function kepalaCabang()
    {
        return $this->belongsTo(User::class, 'kepala_cabang_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'cabang_user')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'lokasi_id');
    }

    public function karyawans()
    {
        return $this->hasMany(Karyawan::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'lokasi_id');
    }

    public function absenDevices()
    {
        return $this->hasMany(AbsenDevice::class);
    }

    public function faceAttendances()
    {
        return $this->hasMany(FaceAttendance::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class)->orderBy('id');
    }

    /** Hitung jarak (meter) dari koordinat cabang ke titik lain menggunakan Haversine */
    public function hitungJarak(float $lat, float $lng): float
    {
        if (!$this->latitude || !$this->longitude) {
            return 0;
        }

        $earthRadius = 6371000; // meter
        $dLat = deg2rad($lat - $this->latitude);
        $dLng = deg2rad($lng - $this->longitude);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function isLokasiValid(float $lat, float $lng): bool
    {
        if (!$this->latitude || !$this->longitude) {
            return true; // GPS belum diset, izinkan
        }
        return $this->hitungJarak($lat, $lng) <= $this->radius_absen_meter;
    }

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCabangSaja($query)
    {
        return $query->where('tipe', TipeCabang::Cabang);
    }

    public function scopeGudangPusat($query)
    {
        return $query->where('tipe', TipeCabang::GudangPusat);
    }
}
