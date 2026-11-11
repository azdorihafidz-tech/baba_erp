<?php

namespace App\Models;

use App\Traits\FillsDeletedBy;
use App\Traits\HasAuditLog;
use App\Traits\HasCabang;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class RecurringTransaksi extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog, HasCabang, FillsDeletedBy;

    protected $table = 'recurring_transaksis';

    protected $fillable = [
        'nama_template',
        'cabang_id',
        'kas_id',
        'kategori_id',
        'tipe',
        'jumlah',
        'keterangan',
        'frekuensi',
        'tanggal_jatuh_tempo',
        'tanggal_mulai',
        'tanggal_akhir',
        'tanggal_terakhir_generate',
        'is_active',
        'auto_approve',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_mulai'              => 'date',
        'tanggal_akhir'              => 'date',
        'tanggal_terakhir_generate'  => 'date',
        'jumlah'                     => 'decimal:2',
        'is_active'                  => 'boolean',
        'auto_approve'               => 'boolean',
    ];

    // ===== RELATIONS =====

    public function kas()
    {
        return $this->belongsTo(Kas::class);
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriTransaksi::class, 'kategori_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ===== SCOPES =====

    public function scopeAktif($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Template yang jatuh tempo pada tanggal tertentu dan belum di-generate bulan/hari ini.
     */
    public function scopeJatuhTempo($query, ?Carbon $date = null)
    {
        $date = $date ?? Carbon::today();

        return $query->aktif()
            ->where('tanggal_jatuh_tempo', $date->day)
            ->where('tanggal_mulai', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('tanggal_akhir')
                  ->orWhere('tanggal_akhir', '>=', $date->toDateString());
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('tanggal_terakhir_generate')
                  ->orWhereRaw(
                      '(YEAR(tanggal_terakhir_generate) != ? OR MONTH(tanggal_terakhir_generate) != ?)',
                      [$date->year, $date->month]
                  );
            });
    }

    // ===== METHODS =====

    /**
     * Generate 1 TransaksiKeuangan dari template ini.
     */
    public function generateTransaksi(?Carbon $date = null): TransaksiKeuangan
    {
        $date = $date ?? Carbon::today();

        $prefix   = 'REC-' . $date->format('Ymd');
        $lastNomor = TransaksiKeuangan::withoutGlobalScopes()
            ->where('nomor_transaksi', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('nomor_transaksi');
        $seq = 1;
        if ($lastNomor && preg_match('/(\d+)$/', $lastNomor, $m)) {
            $seq = (int) $m[1] + 1;
        }

        $trx = TransaksiKeuangan::create([
            'cabang_id'         => $this->cabang_id,
            'kas_id'            => $this->kas_id,
            'nomor_transaksi'   => $prefix . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT),
            'tanggal_transaksi' => $date->toDateString(),
            'tipe'              => $this->tipe,
            'kategori_id'       => $this->kategori_id,
            'keterangan'        => $this->keterangan . ' (Recurring)',
            'jumlah'            => $this->jumlah,
            'created_by'        => $this->created_by ?? auth()->id(),
        ]);

        // Update saldo kas
        if ($this->kas_id) {
            if ($this->tipe === 'pemasukan') {
                Kas::where('id', $this->kas_id)->increment('saldo_sekarang', (float) $this->jumlah);
            } else {
                Kas::where('id', $this->kas_id)->decrement('saldo_sekarang', (float) $this->jumlah);
            }
        }

        return $trx;
    }

    /**
     * Berapa hari sampai jatuh tempo berikutnya.
     */
    public function hariMenujuJatuhTempo(): ?int
    {
        if (!$this->is_active) return null;
        $today    = Carbon::today();
        $thisMonth = Carbon::today()->setDay(min($this->tanggal_jatuh_tempo, $today->daysInMonth));
        $target   = $thisMonth->lt($today) ? $thisMonth->addMonth() : $thisMonth;
        return $today->diffInDays($target);
    }
}
