<?php

namespace App\Models;

use App\Enums\StatusSetoranKasir;
use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setoran extends Model
{
    use HasAuditLog, SoftDeletes, FillsDeletedBy;

    protected $fillable = [
        'cabang_id', 'tanggal', 'disubmit_oleh', 'disubmit_pada',
        'total_penjualan_sistem', 'total_disetor', 'selisih', 'status',
        'disetujui_oleh', 'disetujui_pada', 'ditolak_alasan', 'bukti_foto',
        'catatan_kasir', 'catatan_ho', 'transaksi_out_id', 'transaksi_in_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'disubmit_pada' => 'datetime',
            'disetujui_pada' => 'datetime',
            'total_penjualan_sistem' => 'decimal:2',
            'total_disetor' => 'decimal:2',
            'selisih' => 'decimal:2',
            'status' => StatusSetoranKasir::class,
        ];
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class);
    }

    public function disubmitOleh()
    {
        return $this->belongsTo(User::class, 'disubmit_oleh');
    }

    public function disetujuiOleh()
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function details()
    {
        return $this->hasMany(SetoranDetail::class);
    }

    public function approvals()
    {
        return $this->hasMany(SetoranApproval::class)->orderBy('dilakukan_pada');
    }

    public function transaksiOut()
    {
        return $this->belongsTo(TransaksiKeuangan::class, 'transaksi_out_id');
    }

    public function transaksiIn()
    {
        return $this->belongsTo(TransaksiKeuangan::class, 'transaksi_in_id');
    }

    public function scopeMenunggu($query)
    {
        return $query->where('status', StatusSetoranKasir::Menunggu);
    }
}
