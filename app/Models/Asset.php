<?php

namespace App\Models;

use App\Enums\KondisiAset;
use App\Enums\MetodePenyusutan;
use App\Enums\StatusAset;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected array $auditedAttributes = [
        'kode_aset', 'nama_aset', 'kategori_aset_id', 'lokasi_id',
        'harga_perolehan', 'nilai_residu', 'nilai_buku',
        'kondisi', 'status', 'umur_ekonomis_bulan', 'metode_penyusutan',
        'tanggal_perolehan',
    ];

    protected $fillable = [
        'kode_aset',
        'nama_aset',
        'kategori_aset_id',
        'lokasi_id',
        'tanggal_perolehan',
        'harga_perolehan',
        'nilai_residu',
        'umur_ekonomis_bulan',
        'metode_penyusutan',
        'tarif_penyusutan',
        'estimasi_produksi_total',
        'kondisi',
        'status',
        'nilai_buku',
        'foto',
        'catatan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kondisi' => KondisiAset::class,
            'status' => StatusAset::class,
            'metode_penyusutan' => MetodePenyusutan::class,
            'tanggal_perolehan' => 'date',
            'harga_perolehan' => 'decimal:2',
            'nilai_residu' => 'decimal:2',
            'nilai_buku' => 'decimal:2',
            'tarif_penyusutan' => 'decimal:4',
            'estimasi_produksi_total' => 'decimal:3',
        ];
    }

    public function kategori()
    {
        return $this->belongsTo(AssetCategory::class, 'kategori_aset_id');
    }

    public function lokasi()
    {
        return $this->belongsTo(Cabang::class, 'lokasi_id');
    }

    public function depreciations()
    {
        return $this->hasMany(AssetDepreciation::class);
    }

    public function mutations()
    {
        return $this->hasMany(AssetMutation::class);
    }

    public function maintenances()
    {
        return $this->hasMany(AssetMaintenance::class);
    }

    public function disposal()
    {
        return $this->hasOne(AssetDisposal::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nilaiPenyusutanPerBulan(): float
    {
        if ($this->metode_penyusutan === MetodePenyusutan::GarisLurus) {
            return ($this->harga_perolehan - $this->nilai_residu) / $this->umur_ekonomis_bulan;
        }
        return 0;
    }

    public function scopeAktif($query)
    {
        return $query->where('status', StatusAset::Aktif);
    }
}
