<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\StokOpname;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 7e — Dashboard Stok Opname (rancangan stok opname §9) + daftar lokasi yang belum opname bulan ini
 * (dipakai juga oleh pengingat akhir bulan). Angka kerugian memakai LaporanKerugianStokService supaya sama dengan laporannya.
 */
class DashboardStokOpnameService
{
    public function __construct(private LaporanKerugianStokService $laporan, private StokOpnameService $opname)
    {
    }

    /** Lokasi aktif yang punya stok (layak di-opname). */
    public static function lokasiBerstok(): Collection
    {
        return Cabang::where('is_active', true)->whereExists(fn ($q) => $q->from('stocks')->join('items', 'items.id', '=', 'stocks.item_id')
            ->whereColumn('stocks.lokasi_id', 'cabangs.id')->where('stocks.qty', '>', 0)->where('items.track_stok', true))
            ->orderBy('nama_cabang')->get();
    }

    /**
     * Status opname bulan berjalan per lokasi: selesai (+akurasi) / diajukan / hitung / belum.
     *
     * @param  Collection<int, Cabang>  $lokasi
     * @return array<int, array{lokasi: Cabang, status: string, opname: ?StokOpname}>
     */
    public function statusBulanIni(Collection $lokasi, ?Carbon $bulan = null): array
    {
        $bulan ??= now();
        $op = StokOpname::whereIn('cabang_id', $lokasi->pluck('id'))->where('status', '!=', 'batal')
            ->whereBetween('created_at', [$bulan->copy()->startOfMonth(), $bulan->copy()->endOfMonth()])->orderByDesc('id')->get()->groupBy('cabang_id');
        $urut = ['selesai' => 0, 'diajukan' => 1, 'hitung' => 2];
        $hasil = [];
        foreach ($lokasi as $c) {
            // status terbaik dulu; di antara yang sama, opname "Semua barang" (lebih mewakili akurasi lokasi) lalu yang terbaru
            $o = $op->get($c->id, collect())->sortBy(fn ($x) => sprintf('%d%d%010d', $urut[$x->status] ?? 9, $x->cakupan === 'semua' ? 0 : 1, 9999999999 - $x->id))->first();
            $hasil[] = ['lokasi' => $c, 'status' => $o?->status ?? 'belum', 'opname' => $o];
        }

        return $hasil;
    }

    /** @param array<int> $lokasiIds */
    public function data(User $u, array $lokasiIds): array
    {
        $lokasi = self::lokasiBerstok()->whereIn('id', $lokasiIds)->values();
        $ini = $this->laporan->laporan(now()->startOfMonth(), now(), $lokasiIds);
        $lalu = $this->laporan->laporan(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth(), $lokasiIds);

        $menunggu = StokOpname::with('cabang')->whereIn('cabang_id', $lokasiIds)->where('status', 'diajukan')->orderBy('diajukan_at')->get()
            ->filter(fn ($o) => $this->opname->boleh($u, $o, 'setujui'))->values();

        // akurasi 6 bulan per lokasi (rata-rata opname selesai per bulan)
        $bulan = collect(range(5, 0))->map(fn ($n) => now()->subMonthsNoOverflow($n)->format('Y-m'));
        $selesai = StokOpname::whereIn('cabang_id', $lokasiIds)->where('status', 'selesai')
            ->where('disetujui_at', '>=', now()->subMonthsNoOverflow(5)->startOfMonth())->get(['cabang_id', 'disetujui_at', 'ringkasan']);
        $akurasi = [];
        foreach ($selesai->groupBy('cabang_id') as $cid => $os) {
            $per = $os->groupBy(fn ($o) => $o->disetujui_at->format('Y-m'));
            $akurasi[] = ['lokasi' => Cabang::find($cid)?->nama_cabang, 'data' => $bulan->map(fn ($b) => $per->has($b) ? round((float) $per[$b]->avg(fn ($o) => (float) ($o->ringkasan['akurasi'] ?? 0)), 1) : null)->all()];
        }

        // 10 barang paling sering selisih (kurang) 6 bulan terakhir
        $sering = DB::table('stok_opname_rincian as r')->join('stok_opname_item as i', 'i.id', '=', 'r.stok_opname_item_id')
            ->join('stok_opname as o', 'o.id', '=', 'i.stok_opname_id')->join('items as b', 'b.id', '=', 'i.item_id')->join('cabangs as c', 'c.id', '=', 'o.cabang_id')
            ->where('o.status', 'selesai')->where('r.penyebab', '!=', 'lebih')->whereIn('o.cabang_id', $lokasiIds ?: [0])
            ->where('o.disetujui_at', '>=', now()->subMonthsNoOverflow(5)->startOfMonth())
            ->groupBy('i.item_id', 'b.nama_item', 'c.nama_cabang', 'i.satuan')
            ->selectRaw('b.nama_item as nama, c.nama_cabang as lokasi, i.satuan, COUNT(DISTINCT o.id) as kali, SUM(r.qty) as qty, SUM(r.nilai) as nilai')
            ->orderByDesc('kali')->orderByDesc('nilai')->limit(10)->get();

        $rusak = \App\Models\StokRusak::whereIn('cabang_id', $lokasiIds ?: [0])->where('status', 'ditahan')->get();
        $grDasar = \App\Models\GantiRugiStok::whereIn('cabang_id', $lokasiIds ?: [0]);
        $grBayar = \App\Models\GantiRugiStokBayar::whereIn('ganti_rugi_stok_id', (clone $grDasar)->select('id'))->where('status', 'dibayar');

        // Tahap 7f: opname member bulan ini (member tidak terikat lokasi → hanya untuk akun semua-cabang)
        $opnameMember = null;
        if ($u->canAccessAllBranches()) {
            $om = \App\Models\OpnameMember::with('member')->where('created_at', '>=', now()->startOfMonth())->get();
            $opnameMember = ['kali' => $om->count(), 'nilai' => round((float) $om->sum('total_nilai'), 2),
                'teratas' => $om->groupBy('member_id')->map(fn ($g) => ['nama' => $g->first()->member?->nama ?? '#' . $g->first()->member_id,
                    'no_id' => $g->first()->member?->no_id, 'nilai' => round((float) $g->sum('total_nilai'), 2)])->sortByDesc('nilai')->take(5)->values()->all()];
        }

        return [
            'opname_member' => $opnameMember,
            'status' => $this->statusBulanIni($lokasi), 'menunggu' => $menunggu, 'bulan' => $bulan->all(), 'akurasi' => $akurasi, 'sering' => $sering,
            'ini' => $ini, 'lalu' => $lalu,
            'rusak' => ['jumlah' => $rusak->count(), 'nilai' => round((float) $rusak->sum(fn ($r) => $r->nilai_sisa), 2), 'tertua' => (int) $rusak->max(fn ($r) => $r->umur_hari)],
            'ganti_rugi' => [
                'dibebankan' => round((float) (clone $grDasar)->where('status', '!=', 'batal')->sum('nilai'), 2),
                'tunai' => round((float) (clone $grBayar)->where('jenis', 'tunai')->sum('jumlah'), 2),
                'potong_gaji' => round((float) (clone $grBayar)->where('jenis', 'potong_gaji')->sum('jumlah'), 2),
                'sisa' => round((float) (clone $grDasar)->where('status', 'berjalan')->get()->sum(fn ($g) => $g->sisa), 2),
            ],
        ];
    }

    /** Dalam jendela pengingat: mulai H-3 sebelum akhir bulan sampai hari terakhir. */
    public static function masaPengingat(?Carbon $hari = null): bool
    {
        $hari ??= now();

        return $hari->day >= $hari->copy()->endOfMonth()->subDays(3)->day;
    }
}
