<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, FillsDeletedBy;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'kode',
        'nama',
        'tipe',
        'subtipe',
        'parent_kode',
        'saldo_normal',
        'level',
        'is_leaf',
        'urutan',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'is_leaf' => 'boolean',
            'level'   => 'integer',
            'urutan'  => 'integer',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_kode', 'kode');
    }

    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_kode', 'kode');
    }

    public function kategoriTransaksis()
    {
        return $this->hasMany(KategoriTransaksi::class, 'kode_akun_coa', 'kode');
    }

    public function getLabelLengkapAttribute(): string
    {
        return "{$this->kode} — {$this->nama}";
    }
}
