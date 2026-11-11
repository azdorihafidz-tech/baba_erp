<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Retur pesanan online (ERP BABA Tahap 2d). Aturan di ReturOnlineService. */
class ReturOnline extends Model
{
    protected $table = 'retur_online';

    public const STATUS = ['diajukan' => 'Diajukan', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak', 'batal' => 'Dibatalkan'];
    public const WARNA = ['diajukan' => 'warning', 'disetujui' => 'success', 'ditolak' => 'danger', 'batal' => 'secondary'];
    public const PENGEMBALIAN = ['potong_tagihan' => 'Memotong tagihan', 'transfer' => 'Dana ditransfer kembali', 'tunai' => 'Dana dikembalikan tunai'];

    protected $fillable = [
        'nomor', 'pesanan_online_id', 'member_id', 'sumber', 'sumber_cabang_id', 'sumber_member_id', 'status', 'alasan', 'foto', 'nilai',
        'pengembalian', 'catatan_penjual', 'diputus_at', 'diputus_oleh_jenis', 'diputus_oleh_id', 'diputus_oleh_nama',
    ];

    protected function casts(): array
    {
        return ['member_id' => 'integer', 'sumber_member_id' => 'integer', 'nilai' => 'decimal:2', 'diputus_at' => 'datetime'];
    }

    public function pesanan() { return $this->belongsTo(PesananOnline::class, 'pesanan_online_id'); }
    public function item()    { return $this->hasMany(ReturOnlineItem::class); }
    public function cabang()  { return $this->belongsTo(Cabang::class, 'sumber_cabang_id'); }
    public function member()  { return $this->belongsTo(MemberAkun::class, 'member_id', 'member_id'); }

    public function getStatusLabelAttribute(): string { return self::STATUS[$this->status] ?? $this->status; }
    public function getStatusWarnaAttribute(): string { return self::WARNA[$this->status] ?? 'secondary'; }
}
