<?php

namespace App\Services;

use App\Enums\StatusOrder;
use App\Enums\TipeTransaksiKeuangan;
use App\Models\Cabang;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\PengaturanUmum;
use App\Models\TransaksiKeuangan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Closing / Tutup Buku — hitung isi laporan closing (harian/mingguan/bulanan)
 * per cabang atau keseluruhan. Murni READ, tidak mengubah data apa pun.
 *
 * Prinsip klasifikasi (sama untuk usaha 1 cabang maupun pusat + N cabang —
 * tidak bergantung pada ada/tidaknya Gudang Pusat):
 * - Penjualan & HPP diambil dari `orders`/`order_items` (status bukan
 *   Pending/Dibatalkan). Transaksi keuangan ber-referensi order TIDAK dihitung
 *   ulang di Laba Rugi supaya omzet tidak dobel.
 * - Mutasi internal (MUTASI-*, SETORKSR-*, SETOR-*) dipisah ke section
 *   sendiri — bukan pemasukan/pengeluaran. Di laporan keseluruhan, kedua
 *   sisinya ikut masuk sehingga saling meniadakan.
 * - Pembelian bahan (PBB), pembelian aset, saldo awal kas, retur pembelian
 *   masuk Arus Kas tapi TIDAK masuk Laba Rugi (biaya bahan sudah diwakili
 *   HPP; aset diwakili penyusutan).
 * - Saldo kas per tanggal dihitung mundur dari saldo sekarang dikurangi
 *   transaksi sesudah tanggal akhir periode. SETOR-IN yang belum diterima
 *   tidak dihitung (kas tujuan memang belum bertambah).
 */
class ClosingService
{
    public const JENIS = ['harian', 'mingguan', 'bulanan'];

    public const KODE_MUTASI = [
        'MUTASI-OUT'   => 'antar_kas',
        'MUTASI-IN'    => 'antar_kas',
        'SETORKSR-OUT' => 'setoran_kasir',
        'SETORKSR-IN'  => 'setoran_kasir',
        'SETOR-OUT'    => 'transfer_dana',
        'SETOR-IN'     => 'transfer_dana',
    ];

    public const LABEL_MUTASI = [
        'antar_kas'     => 'Antar kas dalam cabang',
        'setoran_kasir' => 'Setoran Kasir',
        'transfer_dana' => 'Transfer Dana antar cabang',
    ];

    /** Masuk Arus Kas, tapi bukan pendapatan/beban Laba Rugi. */
    private const KODE_NON_LABA_RUGI = ['SALDO', 'PBB', 'ASET', 'RETUR-BELI'];

    /** Transaksi lama tanpa kategori_id — petakan dari enum kolom `kategori`. */
    private const ENUM_KE_KODE = [
        'penjualan'       => 'PENJ',
        'jasa_giling'     => 'JASA',
        'pembelian_bahan' => 'PBB',
        'gaji'            => 'GAJI',
        'sewa_gedung'     => 'SEWA',
        'penyusutan'      => 'PNYS',
        'operasional'     => 'OPS',
        'pembelian_aset'  => 'ASET',
        'lainnya'         => 'LNY',
        'saldo_awal'      => 'SALDO',
    ];

    private ?Collection $kategoriMap = null;

    // =====================================================================
    // PERIODE
    // =====================================================================

    public static function tanggalMulaiPeriode(): int
    {
        $tgl = (int) (PengaturanUmum::getSetting()->tanggal_mulai_periode ?? 1);

        return max(1, min(28, $tgl));
    }

    /**
     * Rentang periode bulanan. Periode dinamai menurut bulan tempat periode
     * BERAKHIR: tanggal mulai 26 → "Oktober 2026" = 26 Sep s/d 25 Okt 2026.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function rentangBulanan(int $tahun, int $bulan, int $tglMulai): array
    {
        $awalBulan = Carbon::create($tahun, $bulan, 1)->startOfDay();

        if ($tglMulai <= 1) {
            return [$awalBulan, $awalBulan->copy()->endOfMonth()->startOfDay()];
        }

        $start = $awalBulan->copy()->subMonthNoOverflow()->day($tglMulai);
        $end = $awalBulan->copy()->day($tglMulai - 1);

        return [$start, $end];
    }

    /** Bulan periode (tgl 1 bulan itu) yang memuat tanggal $date. */
    public static function bulanPeriodeUntuk(Carbon $date, int $tglMulai): Carbon
    {
        $bulan = $date->copy()->startOfMonth();
        if ($tglMulai > 1 && $date->day >= $tglMulai) {
            $bulan->addMonthNoOverflow();
        }

        return $bulan;
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string, jenis: string, tanggal: string, bulan: string}
     */
    public function resolvePeriode(string $jenis, ?string $tanggal = null, ?string $bulan = null): array
    {
        $jenis = in_array($jenis, self::JENIS, true) ? $jenis : 'bulanan';
        $tglMulai = self::tanggalMulaiPeriode();

        $acuan = $this->parseTanggal($tanggal) ?? Carbon::today();

        if ($jenis === 'harian') {
            $start = $acuan->copy()->startOfDay();
            $end = $start->copy();
            $label = $this->fmt($start, 'l, d F Y');
        } elseif ($jenis === 'mingguan') {
            $start = $acuan->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $end = $start->copy()->addDays(6);
            $label = 'Minggu ' . $this->fmt($start, 'd M') . ' – ' . $this->fmt($end, 'd M Y');
        } else {
            $bulanAcuan = $this->parseBulan($bulan) ?? self::bulanPeriodeUntuk(Carbon::today(), $tglMulai);
            [$start, $end] = self::rentangBulanan($bulanAcuan->year, $bulanAcuan->month, $tglMulai);
            $label = $this->fmt($bulanAcuan, 'F Y');
            if ($tglMulai > 1) {
                $label .= ' (' . $this->fmt($start, 'd M') . ' – ' . $this->fmt($end, 'd M Y') . ')';
            }
            $acuan = $bulanAcuan;
        }

        return [
            'jenis'   => $jenis,
            'start'   => $start,
            'end'     => $end,
            'label'   => $label,
            'tanggal' => ($jenis === 'bulanan' ? Carbon::today() : $acuan)->toDateString(),
            'bulan'   => ($jenis === 'bulanan' ? $acuan : self::bulanPeriodeUntuk($acuan, $tglMulai))->format('Y-m'),
        ];
    }

    private function fmt(Carbon $c, string $format): string
    {
        return $c->copy()->locale('id')->translatedFormat($format);
    }

    private function parseTanggal(?string $v): ?Carbon
    {
        if (! $v || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $v)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseBulan(?string $v): ?Carbon
    {
        if (! $v || ! preg_match('/^\d{4}-\d{2}$/', $v)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $v . '-01')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    // =====================================================================
    // HITUNG
    // =====================================================================

    /**
     * @param int|null $cabangId null = keseluruhan (semua cabang)
     */
    public function hitung(Carbon $start, Carbon $end, ?int $cabangId = null): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        $trxAgg = $this->agregatTransaksi($start, $end, $cabangId);
        $penjualan = $this->hitungPenjualan($start, $end, $cabangId);
        $labaRugi = $this->hitungLabaRugi($penjualan, $trxAgg, $cabangId === null);
        $arusKas = $this->hitungArusKas($trxAgg);
        $mutasi = $this->hitungMutasi($start, $end, $cabangId, $trxAgg);
        $saldoKas = $this->hitungSaldoKas($end, $cabangId, $trxAgg);

        return [
            'meta' => [
                'start'       => $start->toDateString(),
                'end'         => $end->toDateString(),
                'cabang_id'   => $cabangId,
                'cabang_nama' => $cabangId ? (Cabang::find($cabangId)?->nama_cabang ?? '-') : 'Semua Cabang',
                'dihitung_pada' => now()->toDateTimeString(),
                'is_realtime_stok' => $end->gte(Carbon::today()),
            ],
            'penjualan'  => $penjualan,
            'laba_rugi'  => $labaRugi,
            'arus_kas'   => $arusKas,
            'saldo_kas'  => $saldoKas,
            'mutasi'     => $mutasi,
            'stok'       => $this->hitungStok($cabangId),
            'setoran'    => $this->hitungSetoran($start, $end, $cabangId, $mutasi),
            'per_cabang' => $cabangId ? [] : $this->hitungPerCabang($start, $end, $trxAgg),
        ];
    }

    // ---------------------------------------------------------------------
    // Klasifikasi kategori
    // ---------------------------------------------------------------------

    private function kategoriMap(): Collection
    {
        return $this->kategoriMap ??= KategoriTransaksi::withTrashed()
            ->get(['id', 'kode', 'nama', 'parent_id'])
            ->keyBy('id');
    }

    /** Kode "efektif": kode kategori khusus (atau kode induknya), fallback enum lama. */
    private function kodeEfektif(?int $kategoriId, ?string $enumKategori): string
    {
        $khusus = array_merge(array_keys(self::KODE_MUTASI), self::KODE_NON_LABA_RUGI, ['PENJ', 'JASA']);

        if ($kategoriId && ($k = $this->kategoriMap()->get($kategoriId))) {
            if (in_array($k->kode, $khusus, true)) {
                return $k->kode;
            }
            $induk = $k->parent_id ? $this->kategoriMap()->get($k->parent_id) : null;
            if ($induk && in_array($induk->kode, $khusus, true)) {
                return $induk->kode;
            }

            return (string) $k->kode;
        }

        return self::ENUM_KE_KODE[$enumKategori] ?? 'LNY';
    }

    private function labelKategori(?int $kategoriId, ?string $enumKategori): string
    {
        if ($kategoriId && ($k = $this->kategoriMap()->get($kategoriId))) {
            return $k->nama;
        }

        return \App\Enums\KategoriTransaksi::tryFrom((string) $enumKategori)?->label() ?? 'Lainnya';
    }

    /**
     * Agregat transaksi keuangan periode ini — 1 baris per kombinasi
     * kategori/tipe/kas/cabang, lalu diklasifikasikan di PHP.
     */
    private function agregatTransaksi(Carbon $start, Carbon $end, ?int $cabangId): Collection
    {
        $pemasukan = TipeTransaksiKeuangan::Pemasukan->value;

        return DB::table('transaksi_keuangans')
            ->whereNull('deleted_at')
            ->whereBetween('tanggal_transaksi', [$start->toDateString(), $end->toDateString()])
            ->tap(fn ($q) => \App\Support\KeuanganBaba::saring($q, 'cabang_id', $cabangId))
            ->selectRaw("kategori_id, kategori, tipe, kas_id, cabang_id,
                CASE WHEN referensi_type = 'order' THEN 1 ELSE 0 END as is_order,
                CASE WHEN referensi_type IN ('pesanan_online_bayar', 'pesanan_online_batal', 'retur_online') THEN 1 ELSE 0 END as is_online,
                CASE WHEN status_setoran = 'menunggu_diterima' THEN 1 ELSE 0 END as is_pending,
                SUM(jumlah) as total, COUNT(*) as jumlah_trx")
            ->groupByRaw('kategori_id, kategori, tipe, kas_id, cabang_id, is_order, is_online, is_pending')
            ->get()
            ->map(function ($r) use ($pemasukan) {
                $kode = $this->kodeEfektif($r->kategori_id ? (int) $r->kategori_id : null, $r->kategori);
                $r->kode = $kode;
                $r->label = $this->labelKategori($r->kategori_id ? (int) $r->kategori_id : null, $r->kategori);
                $r->mutasi = self::KODE_MUTASI[$kode] ?? null;
                $r->masuk = $r->tipe === $pemasukan;
                $r->total = (float) $r->total;
                $r->is_order = (bool) $r->is_order;
                $r->is_online = (bool) $r->is_online; // ERP BABA Tahap 4: Pesanan Online gudang = omzet
                $r->is_pending = (bool) $r->is_pending;
                // SETOR-IN yang belum diterima belum menambah saldo kas tujuan.
                $r->efek_kas = $r->kas_id && ! ($r->masuk && $r->is_pending);

                return $r;
            });
    }

    // ---------------------------------------------------------------------
    // Section: Penjualan
    // ---------------------------------------------------------------------

    private function baseOrder(Carbon $start, Carbon $end, ?int $cabangId)
    {
        return DB::table('orders')
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.tanggal_order', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('orders.status', StatusOrder::bukanPenjualan())
            ->tap(fn ($q) => \App\Support\KeuanganBaba::saring($q, 'orders.cabang_id', $cabangId));
    }

    private function hitungPenjualan(Carbon $start, Carbon $end, ?int $cabangId): array
    {
        $r = $this->baseOrder($start, $end, $cabangId)
            ->selectRaw('COUNT(*) as n, SUM(total_harga) as kotor, SUM(diskon) as diskon,
                SUM(service_charge) as service, SUM(take_away_fee) as takeaway, SUM(total_bayar) as bersih')
            ->first();

        $perMetode = DB::table('order_payments')
            ->joinSub($this->baseOrder($start, $end, $cabangId)->select('orders.id'), 'o', 'o.id', '=', 'order_payments.order_id')
            ->whereNull('order_payments.deleted_at')
            ->selectRaw('metode, SUM(jumlah) as total')
            ->groupBy('metode')
            ->pluck('total', 'metode')
            ->map(fn ($v) => (float) $v)
            ->all();

        // Order lama tanpa baris order_payments → pakai tipe_pembayaran order.
        $tanpaRincian = $this->baseOrder($start, $end, $cabangId)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('order_payments')
                ->whereColumn('order_payments.order_id', 'orders.id')->whereNull('order_payments.deleted_at'))
            ->selectRaw('tipe_pembayaran, SUM(total_bayar) as total')
            ->groupBy('tipe_pembayaran')
            ->get();
        foreach ($tanpaRincian as $t) {
            $key = $t->tipe_pembayaran ?: 'lainnya';
            $perMetode[$key] = ($perMetode[$key] ?? 0) + (float) $t->total;
        }

        $perTipe = $this->baseOrder($start, $end, $cabangId)
            ->selectRaw('tipe_transaksi, COUNT(*) as n, SUM(total_bayar) as total')
            ->groupBy('tipe_transaksi')
            ->get()
            ->map(fn ($t) => [
                'tipe'  => $t->tipe_transaksi ?: 'lainnya',
                'label' => \App\Enums\TipeTransaksi::tryFrom((string) $t->tipe_transaksi)?->label() ?? 'Lainnya',
                'jumlah_order' => (int) $t->n,
                'total' => (float) $t->total,
            ])
            ->values()
            ->all();

        $hpp = (float) DB::table('order_items')
            ->joinSub($this->baseOrder($start, $end, $cabangId)->select('orders.id'), 'o', 'o.id', '=', 'order_items.order_id')
            ->whereNull('order_items.deleted_at')
            ->sum('order_items.hpp');

        // 4.49 Tahap B2 — kiriman Gudang → cabang sendiri (dieliminasi di Tutup Buku Keseluruhan)
        $internalOmzet = $internalHpp = 0.0;
        if (\App\Models\Order::adaKolomKiriman()) {
            $internal = $this->baseOrder($start, $end, $cabangId)->where('orders.customer_type', 'internal');
            // ERP BABA Tahap 4: kiriman ke Toko Kemitraan / Refill Member = penjualan sungguhan, bukan internal.
            if ($bukan = \App\Support\KeuanganBaba::idBukanBaba()) {
                $internal->where(fn ($w) => $w->whereNull('orders.outlet_tujuan_id')->orWhereNotIn('orders.outlet_tujuan_id', $bukan));
            }
            $internalOmzet = (float) (clone $internal)->sum('orders.total_bayar');
            $internalHpp = (float) DB::table('order_items')
                ->joinSub((clone $internal)->select('orders.id'), 'o', 'o.id', '=', 'order_items.order_id')
                ->whereNull('order_items.deleted_at')
                ->sum('order_items.hpp');
        }

        $metodeLabel = [];
        foreach ($perMetode as $metode => $total) {
            $metodeLabel[] = [
                'metode' => $metode,
                'label'  => \App\Enums\TipePembayaran::tryFrom((string) $metode)?->label() ?? ucfirst((string) $metode),
                'total'  => $total,
            ];
        }

        return [
            'jumlah_order'   => (int) ($r->n ?? 0),
            'omzet_kotor'    => (float) ($r->kotor ?? 0),
            'diskon'         => (float) ($r->diskon ?? 0),
            'service_charge' => (float) ($r->service ?? 0),
            'take_away_fee'  => (float) ($r->takeaway ?? 0),
            'omzet_bersih'   => (float) ($r->bersih ?? 0),
            'hpp'            => $hpp,
            // ERP BABA Tahap 4: HPP Pesanan Online gudang (pendapatannya ada di transaksi keuangan).
            'hpp_online'     => app(\App\Services\JurnalOnlineService::class)->hpp($start, $end, $cabangId),
            'per_metode'     => $metodeLabel,
            'per_tipe'       => $perTipe,
            'internal'       => ['omzet' => $internalOmzet, 'hpp' => $internalHpp],
        ];
    }

    // ---------------------------------------------------------------------
    // Section: Laba Rugi
    // ---------------------------------------------------------------------

    private function hitungLabaRugi(array $penjualan, Collection $trxAgg, bool $konsolidasi = false): array
    {
        $pendapatanLain = [];
        $beban = [];
        $omzetOnline = 0.0;

        foreach ($trxAgg as $r) {
            if (! empty($r->is_online)) {
                $omzetOnline += $r->total;

                continue;
            }
            if ($r->mutasi || $r->is_order || in_array($r->kode, self::KODE_NON_LABA_RUGI, true)) {
                continue;
            }
            if ($r->masuk) {
                $pendapatanLain[$r->label] = ($pendapatanLain[$r->label] ?? 0) + $r->total;
            } else {
                $beban[$r->label] = ($beban[$r->label] ?? 0) + $r->total;
            }
        }
        arsort($pendapatanLain);
        arsort($beban);

        // 4.49 Tahap B2 — Keseluruhan: kiriman Gudang → cabang dikeluarkan (omzet & HPP-nya)
        // supaya tidak terhitung dobel dengan penjualan cabang (aturan sama dengan coffeeshop).
        $eliminasiOmzet = $konsolidasi ? (float) ($penjualan['internal']['omzet'] ?? 0) : 0.0;
        $eliminasiHpp = $konsolidasi ? (float) ($penjualan['internal']['hpp'] ?? 0) : 0.0;
        $omzet = $penjualan['omzet_bersih'] + $omzetOnline - $eliminasiOmzet;
        $hpp = $penjualan['hpp'] + (float) ($penjualan['hpp_online'] ?? 0) - $eliminasiHpp;
        $labaKotor = $omzet - $hpp;
        $totalPendapatanLain = array_sum($pendapatanLain);
        $totalBeban = array_sum($beban);
        $labaBersih = $labaKotor + $totalPendapatanLain - $totalBeban;

        return [
            'omzet'                 => $omzet,
            'hpp'                   => $hpp,
            'eliminasi_omzet'       => $eliminasiOmzet,
            'eliminasi_hpp'         => $eliminasiHpp,
            'laba_kotor'            => $labaKotor,
            'margin_kotor_persen'   => $omzet > 0 ? round($labaKotor / $omzet * 100, 1) : null,
            'pendapatan_lain'       => $this->toRows($pendapatanLain),
            'total_pendapatan_lain' => $totalPendapatanLain,
            'beban'                 => $this->toRows($beban),
            'total_beban'           => $totalBeban,
            'laba_bersih'           => $labaBersih,
        ];
    }

    // ---------------------------------------------------------------------
    // Section: Arus Kas (hanya transaksi yang benar-benar menyentuh kas)
    // ---------------------------------------------------------------------

    private function hitungArusKas(Collection $trxAgg): array
    {
        $masuk = [];
        $keluar = [];

        foreach ($trxAgg as $r) {
            if ($r->mutasi || ! $r->efek_kas) {
                continue;
            }
            $label = $r->is_order ? 'Penjualan (POS)' : $r->label;
            if ($r->masuk) {
                $masuk[$label] = ($masuk[$label] ?? 0) + $r->total;
            } else {
                $keluar[$label] = ($keluar[$label] ?? 0) + $r->total;
            }
        }
        arsort($masuk);
        arsort($keluar);

        $totalMasuk = array_sum($masuk);
        $totalKeluar = array_sum($keluar);

        return [
            'pemasukan'         => $this->toRows($masuk),
            'total_pemasukan'   => $totalMasuk,
            'pengeluaran'       => $this->toRows($keluar),
            'total_pengeluaran' => $totalKeluar,
            'net'               => $totalMasuk - $totalKeluar,
        ];
    }

    // ---------------------------------------------------------------------
    // Section: Saldo Kas per kas
    // ---------------------------------------------------------------------

    private function hitungSaldoKas(Carbon $end, ?int $cabangId, Collection $trxAgg): array
    {
        $kasList = Kas::with('cabang:id,nama_cabang')
            ->tap(fn ($q) => \App\Support\KeuanganBaba::saring($q, 'cabang_id', $cabangId))
            ->orderBy('cabang_id')->orderBy('tipe_kas')->orderBy('nama_kas')
            ->get();

        $pemasukan = TipeTransaksiKeuangan::Pemasukan->value;
        $netSesudah = DB::table('transaksi_keuangans')
            ->whereNull('deleted_at')
            ->whereNotNull('kas_id')
            ->where('tanggal_transaksi', '>', $end->toDateString())
            ->tap(fn ($q) => \App\Support\KeuanganBaba::saring($q, 'cabang_id', $cabangId))
            ->whereRaw("NOT (tipe = ? AND COALESCE(status_setoran, '') = 'menunggu_diterima')", [$pemasukan])
            ->selectRaw('kas_id, SUM(CASE WHEN tipe = ? THEN jumlah ELSE -jumlah END) as net', [$pemasukan])
            ->groupBy('kas_id')
            ->pluck('net', 'kas_id');

        $perKas = [];
        foreach ($trxAgg as $r) {
            if (! $r->efek_kas) {
                continue;
            }
            $k = $perKas[$r->kas_id] ??= ['masuk' => 0.0, 'keluar' => 0.0, 'mutasi_masuk' => 0.0, 'mutasi_keluar' => 0.0];
            $key = ($r->mutasi ? 'mutasi_' : '') . ($r->masuk ? 'masuk' : 'keluar');
            $k[$key] += $r->total;
            $perKas[$r->kas_id] = $k;
        }

        $rows = [];
        $total = ['tunai' => $this->kosongSaldo(), 'bank' => $this->kosongSaldo()];
        foreach ($kasList as $kas) {
            $g = $perKas[$kas->id] ?? ['masuk' => 0.0, 'keluar' => 0.0, 'mutasi_masuk' => 0.0, 'mutasi_keluar' => 0.0];
            $akhir = (float) $kas->saldo_sekarang - (float) ($netSesudah[$kas->id] ?? 0);
            $awal = $akhir - ($g['masuk'] - $g['keluar'] + $g['mutasi_masuk'] - $g['mutasi_keluar']);

            $adaAktivitas = abs($awal) > 0.004 || abs($akhir) > 0.004 || array_sum($g) > 0;
            if (! $kas->is_active && ! $adaAktivitas) {
                continue;
            }

            $grup = $kas->tipe_kas === 'tunai' ? 'tunai' : 'bank';
            $row = [
                'kas_id'        => $kas->id,
                'nama_kas'      => $kas->nama_kas,
                'cabang'        => $kas->cabang?->nama_cabang ?? '-',
                'grup'          => $grup,
                'saldo_awal'    => $awal,
                'masuk'         => $g['masuk'],
                'keluar'        => $g['keluar'],
                'mutasi_masuk'  => $g['mutasi_masuk'],
                'mutasi_keluar' => $g['mutasi_keluar'],
                'saldo_akhir'   => $akhir,
            ];
            $rows[] = $row;
            foreach (['saldo_awal', 'masuk', 'keluar', 'mutasi_masuk', 'mutasi_keluar', 'saldo_akhir'] as $f) {
                $total[$grup][$f] += $row[$f];
            }
        }

        $gabungan = $this->kosongSaldo();
        foreach ($gabungan as $f => $_) {
            $gabungan[$f] = $total['tunai'][$f] + $total['bank'][$f];
        }

        return [
            'rows'          => $rows,
            'total_tunai'   => $total['tunai'],
            'total_bank'    => $total['bank'],
            'total'         => $gabungan,
        ];
    }

    private function kosongSaldo(): array
    {
        return ['saldo_awal' => 0.0, 'masuk' => 0.0, 'keluar' => 0.0, 'mutasi_masuk' => 0.0, 'mutasi_keluar' => 0.0, 'saldo_akhir' => 0.0];
    }

    // ---------------------------------------------------------------------
    // Section: Mutasi Internal
    // ---------------------------------------------------------------------

    private function hitungMutasi(Carbon $start, Carbon $end, ?int $cabangId, Collection $trxAgg): array
    {
        $ringkasan = [];
        foreach (self::LABEL_MUTASI as $jenis => $label) {
            $ringkasan[$jenis] = ['jenis' => $jenis, 'label' => $label, 'keluar' => 0.0, 'masuk' => 0.0, 'dalam_perjalanan' => 0.0];
        }
        foreach ($trxAgg as $r) {
            if (! $r->mutasi) {
                continue;
            }
            if ($r->masuk && $r->is_pending) {
                $ringkasan[$r->mutasi]['dalam_perjalanan'] += $r->total;
            } elseif ($r->masuk) {
                $ringkasan[$r->mutasi]['masuk'] += $r->total;
            } else {
                $ringkasan[$r->mutasi]['keluar'] += $r->total;
            }
        }
        foreach ($ringkasan as &$g) {
            $g['net'] = $g['masuk'] - $g['keluar'];
        }
        unset($g);

        return [
            'ringkasan'   => array_values($ringkasan),
            'total_keluar' => array_sum(array_column($ringkasan, 'keluar')),
            'total_masuk'  => array_sum(array_column($ringkasan, 'masuk')),
            'total_dalam_perjalanan' => array_sum(array_column($ringkasan, 'dalam_perjalanan')),
            'detail'      => $this->detailMutasi($start, $end, $cabangId),
        ];
    }

    /**
     * Daftar mutasi per pasangan (keluar → masuk), dedup supaya 1 perpindahan
     * tampil 1 baris walau kedua sisinya ada di cakupan laporan.
     */
    private function detailMutasi(Carbon $start, Carbon $end, ?int $cabangId, int $limit = 500): array
    {
        $kategoriIds = $this->kategoriMap()
            ->filter(fn ($k) => isset(self::KODE_MUTASI[$this->kodeEfektif($k->id, null)]))
            ->keys()->all();
        if (empty($kategoriIds)) {
            return [];
        }

        $rows = TransaksiKeuangan::with('kas:id,nama_kas,cabang_id', 'cabang:id,nama_cabang')
            ->whereIn('kategori_id', $kategoriIds)
            ->whereBetween('tanggal_transaksi', [$start->toDateString(), $end->toDateString()])
            ->tap(fn ($q) => \App\Support\KeuanganBaba::saring($q, 'cabang_id', $cabangId))
            ->orderBy('tanggal_transaksi')->orderBy('id')
            ->limit($limit)
            ->get();

        // Kumpulkan id pasangan
        $pairIds = [];
        $setoranKasirIds = [];
        foreach ($rows as $t) {
            if ($t->setoran_pair_id) {
                $pairIds[] = $t->setoran_pair_id;
            } elseif ($t->referensi_type === 'transfer_antar_kas' && $t->referensi_id) {
                $pairIds[] = $t->referensi_id;
            } elseif ($t->referensi_type === 'setoran_kasir' && $t->referensi_id) {
                $setoranKasirIds[] = $t->referensi_id;
            }
        }
        $partners = TransaksiKeuangan::with('kas:id,nama_kas,cabang_id', 'cabang:id,nama_cabang')
            ->whereIn('id', array_unique($pairIds))->get()->keyBy('id');
        $partnersSk = $setoranKasirIds
            ? TransaksiKeuangan::with('kas:id,nama_kas,cabang_id', 'cabang:id,nama_cabang')
                ->where('referensi_type', 'setoran_kasir')->whereIn('referensi_id', array_unique($setoranKasirIds))->get()
            : collect();

        $pemasukan = TipeTransaksiKeuangan::Pemasukan->value;
        $hasil = [];
        foreach ($rows as $t) {
            $partner = null;
            if ($t->setoran_pair_id) {
                $partner = $partners->get($t->setoran_pair_id);
            } elseif ($t->referensi_type === 'transfer_antar_kas') {
                $partner = $partners->get($t->referensi_id);
            } elseif ($t->referensi_type === 'setoran_kasir') {
                $partner = $partnersSk->first(fn ($p) => $p->referensi_id == $t->referensi_id && $p->id !== $t->id);
            }

            $isMasuk = $t->tipe?->value === $pemasukan;
            $out = $isMasuk ? $partner : $t;
            $in = $isMasuk ? $t : $partner;
            $key = ($out?->id ?? 'x') . '-' . ($in?->id ?? 'x');
            if (isset($hasil[$key])) {
                continue;
            }

            $outInScope = $out && (! $cabangId || $out->cabang_id == $cabangId);
            $inScope = $in && (! $cabangId || $in->cabang_id == $cabangId);

            $kode = $this->kodeEfektif($t->kategori_id, $t->kategori?->value);
            $hasil[$key] = [
                'tanggal' => $t->tanggal_transaksi?->toDateString(),
                'jenis'   => self::LABEL_MUTASI[self::KODE_MUTASI[$kode]] ?? '-',
                'dari'    => $out ? $this->namaKas($out) : '-',
                'ke'      => $in ? $this->namaKas($in) : '-',
                'jumlah'  => (float) $t->jumlah,
                'arah'    => $outInScope && $inScope ? 'internal' : ($outInScope ? 'keluar' : 'masuk'),
                'status'  => ($in?->status_setoran?->value ?? null) === 'menunggu_diterima' ? 'Belum diterima' : 'Selesai',
                'keterangan' => $t->keterangan,
            ];
        }

        return array_values($hasil);
    }

    private function namaKas(TransaksiKeuangan $t): string
    {
        $kas = $t->kas?->nama_kas ?? 'Tanpa kas';

        return $kas . ' (' . ($t->cabang?->nama_cabang ?? '-') . ')';
    }

    // ---------------------------------------------------------------------
    // Section: Stok (nilai persediaan SAAT INI — dibekukan saat closing)
    // ---------------------------------------------------------------------

    private function hitungStok(?int $cabangId): array
    {
        $perTipe = DB::table('stock_batches')
            ->join('items', 'items.id', '=', 'stock_batches.item_id')
            ->where('stock_batches.qty_sisa', '>', 0)
            ->whereNull('stock_batches.deleted_at')
            ->tap(fn ($q) => \App\Support\KeuanganBaba::saring($q, 'stock_batches.lokasi_id', $cabangId))
            ->selectRaw('items.tipe, SUM(stock_batches.qty_sisa * stock_batches.harga_beli_per_unit) as nilai')
            ->groupBy('items.tipe')
            ->pluck('nilai', 'tipe');

        // Bucket sama dengan NeracaService::hitungAset()
        $bahanBaku = (float) ($perTipe['bahan_baku'] ?? 0) + (float) ($perTipe['lainnya'] ?? 0) + (float) ($perTipe['tambahan_gratis'] ?? 0);
        $barangJadi = (float) ($perTipe['produk_jual'] ?? 0) + (float) ($perTipe['produk_tambahan'] ?? 0) + (float) ($perTipe['produk_jadi'] ?? 0);
        $kemasan = (float) ($perTipe['kemasan'] ?? 0);

        return [
            'bahan_baku'  => $bahanBaku,
            'barang_jadi' => $barangJadi,
            'kemasan'     => $kemasan,
            'total'       => $bahanBaku + $barangJadi + $kemasan,
        ];
    }

    // ---------------------------------------------------------------------
    // Section: Setoran
    // ---------------------------------------------------------------------

    private function hitungSetoran(Carbon $start, Carbon $end, ?int $cabangId, array $mutasi): array
    {
        $perStatus = DB::table('setorans')
            ->whereNull('deleted_at')
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->tap(fn ($q) => \App\Support\KeuanganBaba::saring($q, 'cabang_id', $cabangId))
            ->selectRaw('status, COUNT(*) as n, SUM(total_disetor) as total, SUM(selisih) as selisih')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $status = [];
        foreach (\App\Enums\StatusSetoranKasir::cases() as $case) {
            $s = $perStatus->get($case->value);
            $status[] = [
                'status' => $case->value,
                'label'  => $case->label(),
                'jumlah' => (int) ($s->n ?? 0),
                'total'  => (float) ($s->total ?? 0),
                'selisih' => (float) ($s->selisih ?? 0),
            ];
        }

        // Hari ber-penjualan tunai yang belum ada Setoran Kasir sama sekali.
        $hariBelumSetor = 0;
        $pakaiSetoranKasir = Cabang::gudangPusat()->exists();
        if ($pakaiSetoranKasir) {
            $hariBelumSetor = DB::table('orders')
                ->whereNull('orders.deleted_at')
                ->whereBetween('orders.tanggal_order', [$start->toDateString(), $end->toDateString()])
                ->whereNotIn('orders.status', StatusOrder::bukanPenjualan())
                ->tap(fn ($q) => \App\Support\KeuanganBaba::saring($q, 'orders.cabang_id', $cabangId))
                ->join('cabangs', 'cabangs.id', '=', 'orders.cabang_id')
                ->where('cabangs.tipe', '!=', 'gudang_pusat')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('setorans')
                    ->whereColumn('setorans.cabang_id', 'orders.cabang_id')
                    ->whereColumn('setorans.tanggal', 'orders.tanggal_order')
                    ->whereNull('setorans.deleted_at'))
                ->selectRaw('COUNT(DISTINCT orders.cabang_id, orders.tanggal_order) as n')
                ->value('n') ?? 0;
        }

        return [
            'pakai_setoran_kasir' => $pakaiSetoranKasir,
            'per_status'          => $status,
            'hari_belum_setor'    => (int) $hariBelumSetor,
            'transfer_dana_belum_diterima' => (float) collect($mutasi['ringkasan'])->firstWhere('jenis', 'transfer_dana')['dalam_perjalanan'],
        ];
    }

    // ---------------------------------------------------------------------
    // Section: Per cabang (hanya di laporan keseluruhan)
    // ---------------------------------------------------------------------

    private function hitungPerCabang(Carbon $start, Carbon $end, Collection $trxAgg): array
    {
        $orders = $this->baseOrder($start, $end, null)
            ->selectRaw('cabang_id, COUNT(*) as n, SUM(total_bayar) as omzet')
            ->groupBy('cabang_id')->get()->keyBy('cabang_id');

        $hpp = DB::table('order_items')
            ->joinSub($this->baseOrder($start, $end, null)->select('orders.id', 'orders.cabang_id'), 'o', 'o.id', '=', 'order_items.order_id')
            ->whereNull('order_items.deleted_at')
            ->selectRaw('o.cabang_id, SUM(order_items.hpp) as hpp')
            ->groupBy('o.cabang_id')->pluck('hpp', 'cabang_id');

        $lain = [];
        foreach ($trxAgg as $r) {
            if (! empty($r->is_online)) {
                $c = $lain[$r->cabang_id] ??= ['pendapatan_lain' => 0.0, 'beban' => 0.0];
                $c['online'] = ($c['online'] ?? 0.0) + $r->total;
                $lain[$r->cabang_id] = $c;

                continue;
            }
            if ($r->mutasi || $r->is_order || in_array($r->kode, self::KODE_NON_LABA_RUGI, true)) {
                continue;
            }
            $c = $lain[$r->cabang_id] ??= ['pendapatan_lain' => 0.0, 'beban' => 0.0];
            $c[$r->masuk ? 'pendapatan_lain' : 'beban'] += $r->total;
            $lain[$r->cabang_id] = $c;
        }

        $rows = [];
        foreach (Cabang::orderBy('id')->get(['id', 'nama_cabang', 'tipe', 'is_active', 'jenis_toko']) as $cabang) {
            if (! \App\Support\KeuanganBaba::milikBaba($cabang)) {
                continue; // ERP BABA Tahap 4: toko mitra di luar Tutup Buku Keseluruhan
            }
            $o = $orders->get($cabang->id);
            $omzet = (float) ($o->omzet ?? 0) + (float) ($lain[$cabang->id]['online'] ?? 0);
            $h = (float) ($hpp[$cabang->id] ?? 0) + app(\App\Services\JurnalOnlineService::class)->hpp($start, $end, $cabang->id);
            $pl = $lain[$cabang->id]['pendapatan_lain'] ?? 0.0;
            $bb = $lain[$cabang->id]['beban'] ?? 0.0;
            if (! $cabang->is_active && $omzet == 0 && $pl == 0 && $bb == 0) {
                continue;
            }
            $rows[] = [
                'cabang_id'    => $cabang->id,
                'nama_cabang'  => $cabang->nama_cabang,
                'jumlah_order' => (int) ($o->n ?? 0),
                'omzet'        => $omzet,
                'hpp'          => $h,
                'laba_kotor'   => $omzet - $h,
                'pendapatan_lain' => $pl,
                'beban'        => $bb,
                'laba_bersih'  => $omzet - $h + $pl - $bb,
            ];
        }

        return $rows;
    }

    /**
     * Hari (N hari terakhir, tidak termasuk hari ini) yang ada penjualannya
     * tapi belum di-closing — untuk pengingat di dashboard.
     *
     * @param int[] $cabangIds
     * @return array<int, array{cabang_id: int, nama_cabang: string, tanggal: string}>
     */
    public function hariBelumClosing(array $cabangIds, int $hari = 7): array
    {
        if (empty($cabangIds)) {
            return [];
        }

        $start = Carbon::today()->subDays($hari);
        $end = Carbon::yesterday();

        $hariJualan = DB::table('orders')
            ->join('cabangs', 'cabangs.id', '=', 'orders.cabang_id')
            ->whereNull('orders.deleted_at')
            ->whereIn('orders.cabang_id', $cabangIds)
            ->whereBetween('orders.tanggal_order', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('orders.status', StatusOrder::bukanPenjualan())
            ->selectRaw('orders.cabang_id, cabangs.nama_cabang, orders.tanggal_order')
            ->distinct()
            ->orderBy('orders.tanggal_order')
            ->get();

        if ($hariJualan->isEmpty()) {
            return [];
        }

        $closings = \App\Models\Closing::final()
            ->whereIn('cabang_id', $cabangIds)
            ->whereDate('periode_mulai', '<=', $end->toDateString())
            ->whereDate('periode_selesai', '>=', $start->toDateString())
            ->get(['cabang_id', 'periode_mulai', 'periode_selesai']);

        return $hariJualan
            ->reject(fn ($r) => $closings->contains(fn ($c) => $c->cabang_id == $r->cabang_id
                && $c->periode_mulai->toDateString() <= $r->tanggal_order
                && $c->periode_selesai->toDateString() >= $r->tanggal_order))
            ->map(fn ($r) => ['cabang_id' => (int) $r->cabang_id, 'nama_cabang' => $r->nama_cabang, 'tanggal' => (string) $r->tanggal_order])
            ->values()
            ->all();
    }

    private function toRows(array $map): array
    {
        $rows = [];
        foreach ($map as $label => $total) {
            $rows[] = ['label' => (string) $label, 'total' => (float) $total];
        }

        return $rows;
    }
}
