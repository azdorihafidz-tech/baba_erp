<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pesanan member dari menu Belanja aplikasi member (ERP BABA Tahap 2b). Alur: lihat PesananOnlineService. */
class PesananOnline extends Model
{
    protected $table = 'pesanan_online';

    public const STATUS = [
        'menunggu_ongkir' => 'Menunggu ongkir',
        'menunggu_bayar'  => 'Menunggu pembayaran',
        'menunggu_cek'    => 'Bukti bayar dicek',
        'disiapkan'       => 'Disiapkan',
        'dikirim'         => 'Dikirim',
        'siap_diambil'    => 'Siap diambil',
        'diterima'        => 'Diterima',
        'batal'           => 'Dibatalkan',
    ];

    public const WARNA = [
        'menunggu_ongkir' => 'warning', 'menunggu_bayar' => 'warning', 'menunggu_cek' => 'info', 'disiapkan' => 'primary',
        'dikirim' => 'primary', 'siap_diambil' => 'primary', 'diterima' => 'success', 'batal' => 'secondary',
    ];

    public const STATUS_BAYAR = ['belum' => 'Belum dibayar', 'menunggu_cek' => 'Bukti dicek', 'lunas' => 'Lunas'];

    /** Status yang masih berjalan (belum diterima / batal). */
    public const BERJALAN = ['menunggu_ongkir', 'menunggu_bayar', 'menunggu_cek', 'disiapkan', 'dikirim', 'siap_diambil'];

    protected $fillable = [
        'nomor', 'member_id', 'sumber', 'sumber_cabang_id', 'sumber_member_id', 'cara_ambil', 'kirim_nama', 'kirim_hp', 'kirim_alamat',
        'catatan', 'cara_bayar', 'subtotal', 'ongkir', 'total', 'status', 'status_bayar', 'batas_bayar_at', 'jatuh_tempo', 'lunas_at',
        'kurir', 'nomor_resi', 'stok_keluar', 'dikirim_at', 'diterima_at', 'diterima_otomatis', 'batal_at', 'batal_alasan',
    ];

    protected function casts(): array
    {
        return [
            'member_id' => 'integer', 'sumber_member_id' => 'integer', 'subtotal' => 'decimal:2', 'ongkir' => 'decimal:2', 'total' => 'decimal:2',
            'stok_keluar' => 'boolean', 'diterima_otomatis' => 'boolean', 'batas_bayar_at' => 'datetime', 'jatuh_tempo' => 'date',
            'lunas_at' => 'datetime', 'dikirim_at' => 'datetime', 'diterima_at' => 'datetime', 'batal_at' => 'datetime',
        ];
    }

    public function item()   { return $this->hasMany(PesananOnlineItem::class); }
    public function bayar()  { return $this->hasMany(PesananOnlineBayar::class)->orderByDesc('id'); }
    public function log()    { return $this->hasMany(PesananOnlineLog::class)->orderBy('id'); }
    public function cabang() { return $this->belongsTo(Cabang::class, 'sumber_cabang_id'); }
    public function member() { return $this->belongsTo(MemberAkun::class, 'member_id', 'member_id'); }

    public function getStatusLabelAttribute(): string { return self::STATUS[$this->status] ?? $this->status; }
    public function getStatusWarnaAttribute(): string { return self::WARNA[$this->status] ?? 'secondary'; }
    public function getStatusBayarLabelAttribute(): string { return self::STATUS_BAYAR[$this->status_bayar] ?? $this->status_bayar; }
    public function getJumlahBotolAttribute(): int { return (int) $this->item->sum('qty'); }

    public function bayarMenunggu(): ?PesananOnlineBayar
    {
        return $this->bayar->firstWhere('status', 'menunggu');
    }
}
