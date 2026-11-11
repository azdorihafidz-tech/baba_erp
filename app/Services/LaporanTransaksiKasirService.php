<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 4.46 — Laporan Transaksi di menu Penjualan (bisa dibuka kasir). Laporan
 * bertingkat: Selama ini (per tahun) → Tahun (per bulan) → Bulan (per hari)
 * → Hari (daftar transaksi + per jam) → struk. Murni READ.
 *
 * Pendapatan = orders.total_bayar (uang dibayar pelanggan, sudah dikurangi
 * diskon order + termasuk service charge/take away fee). Keuntungan =
 * pendapatan − SUM(order_items.hpp) (modal bahan FIFO saat transaksi).
 * Order dibatalkan, dihapus, dan bill tersimpan belum dibayar tidak dihitung
 * (Order::penjualanSah(), 4.45).
 */
class LaporanTransaksiKasirService
{
    public const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /** Nama hari (Carbon dayOfWeek 0 = Minggu). Tidak bergantung APP_LOCALE (bawaan en). */
    public const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    /** "Kamis, 15 Oktober 2026" / "15 Oktober" / "Kam, 15 Okt" tanpa tergantung locale. */
    public static function tanggal(Carbon $t, string $gaya = 'lengkap'): string
    {
        return match ($gaya) {
            'pendek' => mb_substr(self::HARI[$t->dayOfWeek], 0, 3) . ', ' . $t->format('d') . ' ' . mb_substr(self::BULAN[$t->month], 0, 3),
            'tgl_bulan' => $t->format('d') . ' ' . self::BULAN[$t->month],
            default => self::HARI[$t->dayOfWeek] . ', ' . $t->format('d') . ' ' . self::BULAN[$t->month] . ' ' . $t->year,
        };
    }

    /**
     * Baca tingkat laporan dari query string.
     *   ?semua=1            → per tahun
     *   ?tahun=2026         → per bulan
     *   ?tahun=2026&bulan=10 → per hari
     *   ?tanggal=2026-10-15 → daftar transaksi
     *   ?dari=..&sampai=..  → rentang: < 92 hari per hari, lebih panjang per bulan
     *                         (asal_dari/asal_sampai = rentang awal pilihan user,
     *                         dibawa saat turun ke bulan/hari supaya bisa kembali)
     *   (kosong)            → hari ini
     *
     * @return array{level:string,tahun:?int,bulan:?int,tanggal:?Carbon,rentang:?array,asal:?array}
     */
    public function bacaPosisi(Request $request): array
    {
        $rentang = $this->bacaRentang($request->dari, $request->sampai);
        $asal = $this->bacaRentang($request->asal_dari, $request->asal_sampai) ?? $rentang;

        if ($request->filled('tanggal')) {
            $tanggal = $this->bacaTanggal($request->tanggal);

            return ['level' => 'transaksi', 'tahun' => $tanggal->year, 'bulan' => $tanggal->month, 'tanggal' => $tanggal,
                'rentang' => $rentang, 'asal' => $rentang ? $asal : null];
        }

        if ($rentang) {
            if ($rentang['dari']->isSameDay($rentang['sampai'])) {
                $t = $rentang['dari'];

                return ['level' => 'transaksi', 'tahun' => $t->year, 'bulan' => $t->month, 'tanggal' => $t, 'rentang' => null, 'asal' => null];
            }
            $level = $rentang['dari']->diffInDays($rentang['sampai']) < 92 ? 'hari' : 'bulan';

            return ['level' => $level, 'tahun' => null, 'bulan' => null, 'tanggal' => null, 'rentang' => $rentang, 'asal' => $asal];
        }

        if ($request->filled('tahun')) {
            $tahun = (int) $request->tahun;
            abort_unless($tahun >= 2000 && $tahun <= 2100, 404);

            if ($request->filled('bulan')) {
                $bulan = (int) $request->bulan;
                abort_unless($bulan >= 1 && $bulan <= 12, 404);

                return ['level' => 'hari', 'tahun' => $tahun, 'bulan' => $bulan, 'tanggal' => null, 'rentang' => null, 'asal' => null];
            }

            return ['level' => 'bulan', 'tahun' => $tahun, 'bulan' => null, 'tanggal' => null, 'rentang' => null, 'asal' => null];
        }

        if ($request->boolean('semua')) {
            return ['level' => 'tahun', 'tahun' => null, 'bulan' => null, 'tanggal' => null, 'rentang' => null, 'asal' => null];
        }

        return ['level' => 'transaksi', 'tahun' => now()->year, 'bulan' => now()->month, 'tanggal' => today(), 'rentang' => null, 'asal' => null];
    }

    /** Query dasar order sah + kolom hpp per order (subquery, tanpa dobel baris). */
    public function base(?int $cabangId): Builder
    {
        $hpp = DB::table('order_items')
            ->whereNull('deleted_at')
            ->selectRaw('order_id, SUM(hpp) as hpp')
            ->groupBy('order_id');

        return Order::query()
            ->penjualanSah()
            ->leftJoinSub($hpp, 'oi_hpp', 'oi_hpp.order_id', '=', 'orders.id')
            ->when($cabangId, fn ($q) => $q->where('orders.cabang_id', $cabangId))
            ->when(! $cabangId, fn ($q) => self::tanpaInternal($q));
    }

    /**
     * 4.49 Tahap B2 — mode Semua Cabang: kiriman Gudang → cabang sendiri tidak dihitung
     * (barang yang sama dijual lagi oleh cabang → omzet dobel). Per cabang tetap dihitung.
     */
    public static function tanpaInternal($q)
    {
        if (! Order::adaKolomKiriman()) {
            return $q;
        }

        return $q->where(fn ($w) => $w->whereNull('orders.customer_type')->orWhere('orders.customer_type', '!=', 'internal'));
    }

    /**
     * Kiriman antar cabang yang tidak dihitung di mode Semua Cabang (keterangan di halaman).
     *
     * @return array{transaksi:int,pendapatan:float}|null
     */
    public static function internalTidakDihitung(?int $cabangId, ?Carbon $dari = null, ?Carbon $sampai = null): ?array
    {
        if ($cabangId || ! Order::adaKolomKiriman()) {
            return null;
        }
        $r = Order::query()->penjualanSah()->where('orders.customer_type', 'internal')
            ->when($dari && $sampai, fn ($q) => $q->whereBetween('orders.tanggal_order', [$dari->toDateString(), $sampai->toDateString()]))
            ->toBase()->selectRaw('COUNT(*) as n, COALESCE(SUM(orders.total_bayar),0) as total')->first();

        return (int) $r->n > 0 ? ['transaksi' => (int) $r->n, 'pendapatan' => (float) $r->total] : null;
    }

    /**
     * Baris rekap untuk level tahun/bulan/hari — semua periode diisi (nol kalau
     * tidak ada transaksi) supaya grafik tidak bolong. Periode yang belum
     * terjadi (bulan/hari setelah hari ini) tidak ditampilkan.
     *
     * @return Collection<int, array{kunci:int|string,label:string,label_pendek:string,params:array,transaksi:int,pendapatan:float,hpp:float,keuntungan:float}>
     */
    public function rekap(array $posisi, ?int $cabangId): Collection
    {
        if ($posisi['rentang']) {
            return $this->rekapRentang($posisi, $cabangId);
        }

        $level = $posisi['level'];
        $q = $this->base($cabangId);

        if ($level === 'tahun') {
            $kunci = DB::raw('YEAR(orders.tanggal_order)');
        } elseif ($level === 'bulan') {
            $q->whereYear('orders.tanggal_order', $posisi['tahun']);
            $kunci = DB::raw('MONTH(orders.tanggal_order)');
        } else {
            $q->whereYear('orders.tanggal_order', $posisi['tahun'])->whereMonth('orders.tanggal_order', $posisi['bulan']);
            $kunci = DB::raw('DAY(orders.tanggal_order)');
        }

        $data = $q->groupBy($kunci)
            ->selectRaw($kunci->getValue(DB::connection()->getQueryGrammar()) . ' as kunci')
            ->selectRaw('COUNT(*) as transaksi, COALESCE(SUM(orders.total_bayar),0) as pendapatan, COALESCE(SUM(oi_hpp.hpp),0) as hpp')
            ->toBase()->get()->keyBy(fn ($r) => (int) $r->kunci);

        $daftarKunci = match ($level) {
            'tahun' => $this->daftarTahun($data),
            'bulan' => $this->satuSampai($this->batasAkhir($posisi['tahun'], null, 12)),
            default => $this->satuSampai($this->batasAkhir($posisi['tahun'], $posisi['bulan'],
                Carbon::create($posisi['tahun'], $posisi['bulan'], 1)->daysInMonth)),
        };

        return collect($daftarKunci)->map(function ($k) use ($data, $level, $posisi) {
            $r = $data->get($k);
            $pendapatan = (float) ($r->pendapatan ?? 0);
            $hpp = (float) ($r->hpp ?? 0);

            [$label, $pendek, $params] = match ($level) {
                'tahun' => [(string) $k, (string) $k, ['tahun' => $k]],
                'bulan' => [self::BULAN[$k] . ' ' . $posisi['tahun'], mb_substr(self::BULAN[$k], 0, 3),
                    ['tahun' => $posisi['tahun'], 'bulan' => $k]],
                default => (function () use ($k, $posisi) {
                    $tgl = Carbon::create($posisi['tahun'], $posisi['bulan'], $k);

                    return [self::tanggal($tgl), (string) $k, ['tanggal' => $tgl->toDateString()]];
                })(),
            };

            return [
                'kunci'        => $k,
                'label'        => $label,
                'label_pendek' => $pendek,
                'params'       => $params,
                'transaksi'    => (int) ($r->transaksi ?? 0),
                'pendapatan'   => $pendapatan,
                'hpp'          => $hpp,
                'keuntungan'   => $pendapatan - $hpp,
            ];
        })->values();
    }

    /** Daftar transaksi 1 hari (urut jam) untuk level transaksi. */
    public function transaksi(Carbon $tanggal, ?int $cabangId): Collection
    {
        return $this->base($cabangId)
            ->whereDate('orders.tanggal_order', $tanggal->toDateString())
            ->with(['cabang:id,nama_cabang', 'pelanggan:id,nama_pelanggan', 'payments:id,order_id,metode'])
            ->select('orders.*', DB::raw('COALESCE(oi_hpp.hpp,0) as total_hpp'))
            ->orderBy('orders.created_at')
            ->get()
            ->map(function (Order $o) {
                $metode = $o->payments->map(fn ($p) => $p->metode?->label())->filter()->unique()->values();
                if ($metode->isEmpty() && $o->tipe_pembayaran) {
                    $metode = collect([$o->tipe_pembayaran->label()]);
                }
                $pendapatan = (float) $o->total_bayar;
                $hpp = (float) $o->total_hpp;

                return [
                    'id'         => $o->id,
                    'jam'        => $o->created_at?->format('H:i'),
                    'jam_angka'  => (int) ($o->created_at?->format('G') ?? 0),
                    'nomor'      => $o->nomor_order,
                    'cabang'     => $o->cabang?->nama_cabang,
                    'pelanggan'  => $o->pelanggan?->nama_pelanggan ?: ($o->nama_pelanggan ?: 'Umum'),
                    'tipe'       => $o->tipe_transaksi?->label(),
                    'metode'     => $metode->implode(' + '),
                    'pendapatan' => $pendapatan,
                    'hpp'        => $hpp,
                    'keuntungan' => $pendapatan - $hpp,
                ];
            });
    }

    /** Ringkasan per jam (untuk grafik level transaksi) — hanya rentang jam yang ada transaksinya. */
    public function perJam(Collection $transaksi): Collection
    {
        if ($transaksi->isEmpty()) {
            return collect();
        }
        $kelompok = $transaksi->groupBy('jam_angka');

        return collect(range($transaksi->min('jam_angka'), $transaksi->max('jam_angka')))->map(function ($jam) use ($kelompok) {
            $rows = $kelompok->get($jam, collect());

            return [
                'label'        => sprintf('Jam %02d:00–%02d:59', $jam, $jam),
                'label_pendek' => sprintf('%02d:00', $jam),
                'transaksi'    => $rows->count(),
                'pendapatan'   => (float) $rows->sum('pendapatan'),
                'keuntungan'   => (float) $rows->sum('keuntungan'),
            ];
        })->values();
    }

    /** @return array{transaksi:int,pendapatan:float,hpp:float,keuntungan:float} */
    public function total(Collection $rows): array
    {
        return [
            'transaksi'  => (int) $rows->sum(fn ($r) => $r['transaksi'] ?? 1),
            'pendapatan' => (float) $rows->sum('pendapatan'),
            'hpp'        => (float) $rows->sum('hpp'),
            'keuntungan' => (float) $rows->sum('keuntungan'),
        ];
    }

    /**
     * Jejak halaman (breadcrumb) + tujuan tombol Kembali.
     *
     * @return array{jejak:array<int,array{label:string,params:?array}>,kembali:?array}
     */
    public function jejak(array $posisi): array
    {
        $level = $posisi['level'];

        if ($posisi['rentang']) {
            $asal = $posisi['asal'];
            $jejak = [['label' => 'Rentang ' . self::labelRentang($asal['dari'], $asal['sampai']), 'params' => self::paramsRentang($asal, $asal)]];
            if (! self::rentangSama($posisi['rentang'], $asal)) {
                $jejak[] = ['label' => self::labelRentang($posisi['rentang']['dari'], $posisi['rentang']['sampai']),
                    'params' => self::paramsRentang($posisi['rentang'], $asal)];
            }
            if ($level === 'transaksi') {
                $jejak[] = ['label' => self::tanggal($posisi['tanggal'], 'tgl_bulan'), 'params' => []];
            }

            return $this->tutupJejak($jejak);
        }

        $jejak = [['label' => 'Selama ini', 'params' => ['semua' => 1]]];

        if ($level !== 'tahun') {
            $jejak[] = ['label' => (string) $posisi['tahun'], 'params' => ['tahun' => $posisi['tahun']]];
        }
        if (in_array($level, ['hari', 'transaksi'], true)) {
            $jejak[] = ['label' => self::BULAN[$posisi['bulan']], 'params' => ['tahun' => $posisi['tahun'], 'bulan' => $posisi['bulan']]];
        }
        if ($level === 'transaksi') {
            $jejak[] = ['label' => self::tanggal($posisi['tanggal'], 'tgl_bulan'), 'params' => ['tanggal' => $posisi['tanggal']->toDateString()]];
        }

        return $this->tutupJejak($jejak);
    }

    private function tutupJejak(array $jejak): array
    {
        $kembali = count($jejak) > 1 ? $jejak[count($jejak) - 2]['params'] : null;
        $jejak[count($jejak) - 1]['params'] = null; // posisi sekarang tidak diberi link

        return ['jejak' => $jejak, 'kembali' => $kembali];
    }

    /** Judul halaman sesuai posisi, mis. "Oktober 2026". */
    public function judul(array $posisi): string
    {
        if ($posisi['rentang'] && $posisi['level'] !== 'transaksi') {
            return self::labelRentang($posisi['rentang']['dari'], $posisi['rentang']['sampai'])
                . ($posisi['level'] === 'hari' ? ' (per hari)' : ' (per bulan)');
        }

        return match ($posisi['level']) {
            'tahun'     => 'Selama ini (per tahun)',
            'bulan'     => 'Tahun ' . $posisi['tahun'] . ' (per bulan)',
            'hari'      => self::BULAN[$posisi['bulan']] . ' ' . $posisi['tahun'] . ' (per hari)',
            default     => self::tanggal($posisi['tanggal']),
        };
    }

    /** Tombol cepat yang aktif: hari_ini / bulan_ini / tahun_ini / semua / null. */
    public function pilihanCepatAktif(array $posisi): ?string
    {
        $now = now();
        if ($posisi['rentang']) {
            return null;
        }

        return match (true) {
            $posisi['level'] === 'transaksi' && $posisi['tanggal']->isToday() => 'hari_ini',
            $posisi['level'] === 'hari' && $posisi['tahun'] === $now->year && $posisi['bulan'] === $now->month => 'bulan_ini',
            $posisi['level'] === 'bulan' && $posisi['tahun'] === $now->year => 'tahun_ini',
            $posisi['level'] === 'tahun' => 'semua',
            default => null,
        };
    }

    /** Isi awal kotak rentang tanggal: periode yang sedang dibuka. */
    public function isianRentang(array $posisi): array
    {
        if ($posisi['asal']) {
            return [$posisi['asal']['dari'], $posisi['asal']['sampai']];
        }

        return match ($posisi['level']) {
            'transaksi' => [$posisi['tanggal'], $posisi['tanggal']],
            'hari'      => [Carbon::create($posisi['tahun'], $posisi['bulan'], 1), Carbon::create($posisi['tahun'], $posisi['bulan'], 1)->endOfMonth()->startOfDay()],
            'bulan'     => [Carbon::create($posisi['tahun'], 1, 1), Carbon::create($posisi['tahun'], 12, 31)],
            default     => [null, null],
        };
    }

    /** Rekap per hari / per bulan dalam rentang tanggal pilihan user. */
    private function rekapRentang(array $posisi, ?int $cabangId): Collection
    {
        $dari = $posisi['rentang']['dari'];
        $sampai = $posisi['rentang']['sampai']->copy()->min(today());
        $perHari = $posisi['level'] === 'hari';
        $ekspresi = $perHari ? "DATE_FORMAT(orders.tanggal_order, '%Y-%m-%d')" : "DATE_FORMAT(orders.tanggal_order, '%Y-%m')";

        $data = $this->base($cabangId)
            ->whereBetween('orders.tanggal_order', [$dari->toDateString(), $posisi['rentang']['sampai']->toDateString()])
            ->groupBy(DB::raw($ekspresi))
            ->selectRaw("{$ekspresi} as kunci")
            ->selectRaw('COUNT(*) as transaksi, COALESCE(SUM(orders.total_bayar),0) as pendapatan, COALESCE(SUM(oi_hpp.hpp),0) as hpp')
            ->toBase()->get()->keyBy('kunci');

        $baris = collect();
        $t = $perHari ? $dari->copy() : $dari->copy()->startOfMonth();
        while ($t->lte($sampai)) {
            if ($perHari) {
                $kunci = $t->toDateString();
                $label = self::tanggal($t);
                $pendek = $t->format('j') . ' ' . mb_substr(self::BULAN[$t->month], 0, 3);
                $params = ['tanggal' => $kunci] + self::paramsRentang($posisi['rentang'], $posisi['asal']);
            } else {
                $kunci = $t->format('Y-m');
                $label = self::BULAN[$t->month] . ' ' . $t->year;
                $pendek = mb_substr(self::BULAN[$t->month], 0, 3) . ' ' . $t->format('y');
                $sub = ['dari' => $t->copy()->max($dari), 'sampai' => $t->copy()->endOfMonth()->startOfDay()->min($posisi['rentang']['sampai'])];
                $params = self::paramsRentang($sub, $posisi['asal']);
            }
            $r = $data->get($kunci);
            $pendapatan = (float) ($r->pendapatan ?? 0);
            $hpp = (float) ($r->hpp ?? 0);
            $baris->push([
                'kunci' => $kunci, 'label' => $label, 'label_pendek' => $pendek, 'params' => $params,
                'transaksi' => (int) ($r->transaksi ?? 0), 'pendapatan' => $pendapatan, 'hpp' => $hpp, 'keuntungan' => $pendapatan - $hpp,
            ]);
            $perHari ? $t->addDay() : $t->addMonthNoOverflow()->startOfMonth();
        }

        return $baris;
    }

    /** Query string untuk rentang (+ rentang asal kalau berbeda). */
    public static function paramsRentang(array $rentang, ?array $asal): array
    {
        $p = ['dari' => $rentang['dari']->toDateString(), 'sampai' => $rentang['sampai']->toDateString()];
        if ($asal && ! self::rentangSama($rentang, $asal)) {
            $p += ['asal_dari' => $asal['dari']->toDateString(), 'asal_sampai' => $asal['sampai']->toDateString()];
        }

        return $p;
    }

    public static function rentangSama(array $a, array $b): bool
    {
        return $a['dari']->isSameDay($b['dari']) && $a['sampai']->isSameDay($b['sampai']);
    }

    /** "Oktober 2026" (1 bulan penuh) / "1 Okt – 20 Okt 2026" / "1 Okt 2025 – 5 Nov 2026". */
    public static function labelRentang(Carbon $dari, Carbon $sampai): string
    {
        $bln = fn (Carbon $t) => mb_substr(self::BULAN[$t->month], 0, 3);
        if ($dari->isSameDay($sampai)) {
            return self::tanggal($dari, 'tgl_bulan') . ' ' . $dari->year;
        }
        if ($dari->day === 1 && $sampai->isSameDay($dari->copy()->endOfMonth())) {
            return self::BULAN[$dari->month] . ' ' . $dari->year;
        }
        if ($dari->year === $sampai->year) {
            return $dari->format('j') . ' ' . $bln($dari) . ' – ' . $sampai->format('j') . ' ' . $bln($sampai) . ' ' . $sampai->year;
        }

        return $dari->format('j') . ' ' . $bln($dari) . ' ' . $dari->year . ' – ' . $sampai->format('j') . ' ' . $bln($sampai) . ' ' . $sampai->year;
    }

    private function bacaTanggal($nilai): Carbon
    {
        try {
            $t = Carbon::createFromFormat('Y-m-d', (string) $nilai)->startOfDay();
        } catch (\Throwable) {
            abort(404);
        }
        abort_unless($t->year >= 2000 && $t->year <= 2100, 404);

        return $t;
    }

    /** @return array{dari:Carbon,sampai:Carbon}|null — dibalik otomatis kalau terbalik. */
    private function bacaRentang($dari, $sampai): ?array
    {
        if (blank($dari) || blank($sampai)) {
            return null;
        }
        $a = $this->bacaTanggal($dari);
        $b = $this->bacaTanggal($sampai);

        return $a->lte($b) ? ['dari' => $a, 'sampai' => $b] : ['dari' => $b, 'sampai' => $a];
    }

    /** range(1, n) yang aman untuk n = 0 (range(1, 0) di PHP justru [1, 0]). */
    private function satuSampai(int $n): array
    {
        return $n >= 1 ? range(1, $n) : [];
    }

    private function daftarTahun(Collection $data): array
    {
        $awal = $data->keys()->min() ?? now()->year;

        return range(min($awal, now()->year), now()->year);
    }

    /** Batas akhir bulan/hari: periode sekarang dipotong sampai hari ini, periode depan kosong. */
    private function batasAkhir(int $tahun, ?int $bulan, int $maks): int
    {
        $now = now();
        if ($bulan === null) {
            return $tahun < $now->year ? $maks : ($tahun === $now->year ? $now->month : 0);
        }
        $awalBulan = Carbon::create($tahun, $bulan, 1);
        if ($awalBulan->isSameMonth($now)) {
            return $now->day;
        }

        return $awalBulan->lt($now) ? $maks : 0;
    }
}
