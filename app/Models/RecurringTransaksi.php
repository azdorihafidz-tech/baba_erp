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

    // ===== JADWAL (4.51) =====
    // Dulu: frekuensi diabaikan (semua bulanan per tanggal), tanggal 29–31 terlewat di bulan
    // pendek, hari tanpa ada yang membuka aplikasi terlewat, dan pemicu on-request tidak
    // mencatat tanggal_terakhir_generate (cache dihapus → transaksi dobel). Sekarang SATU aturan
    // dipakai cron, pemicu on-request, dan tombol Generate: 1 transaksi per periode jatuh tempo.

    /** Tanggal jatuh tempo pada bulan tertentu (dibatasi ke akhir bulan, mis. 31 → 28/29 Feb). */
    private function tanggalDalamBulan(int $tahun, int $bulan): Carbon
    {
        $awal = Carbon::create($tahun, $bulan, 1)->startOfDay();
        $tgl  = (int) ($this->tanggal_jatuh_tempo ?: Carbon::parse($this->tanggal_mulai)->day);

        return $awal->setDay(min(max(1, $tgl), $awal->daysInMonth));
    }

    /** Jatuh tempo TERAKHIR yang sudah lewat/hari ini (≤ $hari, dalam masa berlaku), atau null. */
    public function jatuhTempoTerakhir(?Carbon $hari = null): ?Carbon
    {
        $hari  = ($hari ?? Carbon::today())->copy()->startOfDay();
        $mulai = Carbon::parse($this->tanggal_mulai)->startOfDay();
        if ($this->tanggal_akhir && $hari->gt(Carbon::parse($this->tanggal_akhir))) {
            $hari = Carbon::parse($this->tanggal_akhir)->startOfDay();
        }
        if ($hari->lt($mulai)) {
            return null;
        }

        if ($this->frekuensi === 'harian') {
            $due = $hari->copy();
        } elseif ($this->frekuensi === 'mingguan') {
            $due = $hari->copy()->subDays(($hari->dayOfWeek - $mulai->dayOfWeek + 7) % 7);
        } elseif ($this->frekuensi === 'tahunan') {
            $due = $this->tanggalDalamBulan($hari->year, $mulai->month);
            if ($due->gt($hari)) {
                $due = $this->tanggalDalamBulan($hari->year - 1, $mulai->month);
            }
        } else { // bulanan
            $due = $this->tanggalDalamBulan($hari->year, $hari->month);
            if ($due->gt($hari)) {
                $lalu = $hari->copy()->startOfMonth()->subMonth();
                $due = $this->tanggalDalamBulan($lalu->year, $lalu->month);
            }
        }

        return $due->lt($mulai) ? null : $due;
    }

    /** Jatuh tempo yang BELUM dibuat transaksinya (null = tidak ada yang perlu dibuat). */
    public function perluDibuat(?Carbon $hari = null): ?Carbon
    {
        if (! $this->is_active) {
            return null;
        }
        $due = $this->jatuhTempoTerakhir($hari);
        if (! $due) {
            return null;
        }
        if ($this->tanggal_terakhir_generate && Carbon::parse($this->tanggal_terakhir_generate)->startOfDay()->gte($due)) {
            return null;
        }

        return $due;
    }

    /** Jatuh tempo berikutnya yang belum dibuat (untuk tampilan "x hari lagi"). */
    public function jatuhTempoBerikutnya(?Carbon $hari = null): ?Carbon
    {
        $hari = ($hari ?? Carbon::today())->copy()->startOfDay();
        if ($due = $this->perluDibuat($hari)) {
            return $due;
        }
        $acuan = $this->jatuhTempoTerakhir($hari) ?? Carbon::parse($this->tanggal_mulai)->startOfDay()->subDay();
        for ($tambah = 1; $tambah <= 370; $tambah++) {
            $calon = $acuan->copy()->addDays($tambah);
            if ($this->tanggal_akhir && $calon->gt(Carbon::parse($this->tanggal_akhir))) {
                return null;
            }
            $due = $this->jatuhTempoTerakhir($calon);
            if ($due && $due->gt($acuan)) {
                return $due;
            }
        }

        return null;
    }

    /**
     * Buat transaksi semua template yang jatuh tempo (dipakai cron & pemicu on-request).
     * Tanggal jatuh tempo yang sudah di-Closing → transaksi dibuat di tanggal $hari.
     *
     * @return int jumlah transaksi dibuat
     */
    public static function jalankanYangJatuhTempo(?Carbon $hari = null): int
    {
        $hari = ($hari ?? Carbon::today())->copy()->startOfDay();
        $dibuat = 0;
        foreach (static::withoutGlobalScopes()->whereNull('deleted_at')->aktif()->get() as $r) {
            $due = $r->perluDibuat($hari);
            if (! $due) {
                continue;
            }
            try {
                DB::transaction(fn () => $r->generateTransaksi($due));
                $dibuat++;
            } catch (\App\Exceptions\PeriodeTerkunciException $e) {
                try {
                    DB::transaction(fn () => $r->generateTransaksi($hari, $due));
                    $dibuat++;
                } catch (\Throwable $e2) {
                    \Illuminate\Support\Facades\Log::info('Recurring #' . $r->id . ' dilewati: ' . $e2->getMessage());
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Recurring #' . $r->id . ' gagal: ' . $e->getMessage());
            }
        }

        return $dibuat;
    }

    // ===== METHODS =====

    /**
     * Generate 1 TransaksiKeuangan dari template ini pada $date, lalu catat periode jatuh tempo
     * yang sudah dibuat ($periode, bawaan = $date) supaya tidak dibuat dua kali.
     */
    public function generateTransaksi(?Carbon $date = null, ?Carbon $periode = null): TransaksiKeuangan
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
        $tercatat = ($periode ?? $date)->copy();
        $lama = $this->tanggal_terakhir_generate ? Carbon::parse($this->tanggal_terakhir_generate) : null;
        $this->forceFill([
            'tanggal_terakhir_generate' => ($lama && $lama->gt($tercatat) ? $lama : $tercatat)->toDateString(),
        ])->saveQuietly();

        return $trx;
    }

    /**
     * Berapa hari sampai jatuh tempo berikutnya.
     */
    public function hariMenujuJatuhTempo(): ?int
    {
        if (!$this->is_active) return null;
        $next = $this->jatuhTempoBerikutnya();

        return $next ? (int) Carbon::today()->diffInDays($next) : null;
    }
}
