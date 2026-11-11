<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriTransaksi extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, FillsDeletedBy;

    protected $table = 'kategori_transaksis';

    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'parent_id',
        'is_system',
        'is_active',
        'urutan',
        'kode_akun_coa',
        'tipe_biaya',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ===== RELATIONS =====

    public function parent()
    {
        return $this->belongsTo(KategoriTransaksi::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(KategoriTransaksi::class, 'parent_id')->orderBy('urutan');
    }

    public function transaksis()
    {
        return $this->hasMany(TransaksiKeuangan::class, 'kategori_id');
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'kode_akun_coa', 'kode');
    }

    // ===== SCOPES =====

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeToplevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeUntukTipe($query, string $tipe)
    {
        return $query->where(function ($q) use ($tipe) {
            $q->where('tipe', $tipe)->orWhere('tipe', 'keduanya');
        });
    }

    // ===== ACCESSORS =====

    /** Nama lengkap: "Induk > Anak" jika punya parent */
    public function getNamaLengkapAttribute(): string
    {
        return $this->parent
            ? $this->parent->nama . ' › ' . $this->nama
            : $this->nama;
    }

    /**
     * Mapping kode → KategoriTransaksi enum lama (untuk backward compat)
     * Digunakan saat menyimpan kategori_id agar kolom enum lama juga terisi.
     */
    public function toEnumValue(): string
    {
        return match($this->kode) {
            'PENJ'  => 'penjualan',
            'JASA'  => 'jasa_giling',
            'PBB'   => 'pembelian_bahan',
            'GAJI'  => 'gaji',
            'SEWA'  => 'sewa_gedung',
            'PNYS'  => 'penyusutan',
            'OPS'   => 'operasional',
            'ASET'  => 'pembelian_aset',
            'SALDO' => 'saldo_awal',
            default => 'lainnya',
        };
    }

    /**
     * Fallback READ-ONLY untuk laporan formal (Neraca/Laba Rugi Formal):
     * sebagian TransaksiKeuangan lama (terutama referensi_type='order' dari
     * POS — PenjualanService::buatOrder() tidak pernah mengisi kategori_id,
     * cuma kolom enum lama `kategori`) tidak punya kategori_id sama sekali.
     * Mapping ini kebalikan dari toEnumValue(), dipakai KHUSUS di query
     * laporan sebagai fallback resolve kode_akun_coa — TIDAK PERNAH dipakai
     * untuk menulis/backfill data historis.
     */
    public static function kodeAkunCoaFromEnum(?string $enumValue): ?string
    {
        return match($enumValue) {
            'penjualan'       => '4-1102',
            'jasa_giling'     => '4-1101',
            'pembelian_bahan' => '5-1101',
            'gaji'            => '6-1101',
            'sewa_gedung'     => '6-1102',
            'penyusutan'      => '6-1104',
            'operasional'     => '6-1301',
            'pembelian_aset'  => '1-2199',
            'saldo_awal'      => null, // saldo awal kas — bukan pendapatan/beban riil
            'lainnya'         => '6-1999',
            default           => null,
        };
    }
}
