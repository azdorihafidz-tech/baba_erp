<?php

namespace App\Services;

use App\Models\StokOpname;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 7b — Laporan Kerugian Stok per periode (rancangan stok opname §8):
 * hasil stok opname yang DISETUJUI dalam periode (tanggal disetujui) per penyebab, per lokasi, per barang, riwayat opname,
 * ditambah Penyesuaian Stok manual di luar opname (transaksi beban/koreksi stok dari menu Penyesuaian Stok).
 * Nilai = harga pokok (sama dengan jurnal). Lokasi disaring pemanggil (controller) sesuai hak lihat user.
 */
class LaporanKerugianStokService
{
    /** @param array<int> $lokasi */
    public function laporan(Carbon $dari, Carbon $sampai, array $lokasi): array
    {
        $mulai = $dari->copy()->startOfDay();
        $akhir = $sampai->copy()->endOfDay();
        $rincian = DB::table('stok_opname_rincian as r')
            ->join('stok_opname_item as i', 'i.id', '=', 'r.stok_opname_item_id')
            ->join('stok_opname as o', 'o.id', '=', 'i.stok_opname_id')
            ->join('items as b', 'b.id', '=', 'i.item_id')
            ->join('cabangs as c', 'c.id', '=', 'o.cabang_id')
            ->where('o.status', 'selesai')->whereBetween('o.disetujui_at', [$mulai, $akhir])->whereIn('o.cabang_id', $lokasi ?: [0])
            ->select('r.penyebab', 'r.qty', 'r.nilai', 'i.satuan', 'i.item_id', 'b.nama_item', 'b.kode_item', 'o.cabang_id', 'c.nama_cabang', 'o.id as opname_id')
            ->get();

        // per penyebab
        $perPenyebab = [];
        foreach (StokOpnameService::PENYEBAB as $k => $p) {
            $rs = $rincian->where('penyebab', $k);
            $qty = [];
            foreach ($rs as $x) {
                $qty[$x->satuan] = ($qty[$x->satuan] ?? 0) + (float) $x->qty;
            }
            $perPenyebab[$k] = ['label' => $p['label'], 'akun' => $p['coa'] . ' ' . $p['nama'], 'nilai' => round((float) $rs->sum('nilai'), 2),
                'barang' => $rs->unique(fn ($x) => $x->cabang_id . '-' . $x->item_id)->count(), 'qty' => $qty];
        }

        // per lokasi
        $opname = StokOpname::with('cabang')->where('status', 'selesai')->whereBetween('disetujui_at', [$mulai, $akhir])->whereIn('cabang_id', $lokasi ?: [0])
            ->orderBy('disetujui_at')->get();
        $perLokasi = [];
        foreach ($opname->groupBy('cabang_id') as $cid => $os) {
            $rs = $rincian->where('cabang_id', $cid);
            $kurang = round((float) $rs->where('penyebab', '!=', 'lebih')->sum('nilai'), 2);
            $lebih = round((float) $rs->where('penyebab', 'lebih')->sum('nilai'), 2);
            $perLokasi[] = ['lokasi' => $os->first()->cabang?->nama_cabang, 'opname' => $os->count(),
                'akurasi' => round((float) $os->avg(fn ($o) => (float) ($o->ringkasan['akurasi'] ?? 0)), 1),
                'nilai_kurang' => $kurang, 'nilai_lebih' => $lebih, 'bersih' => round($lebih - $kurang, 2),
                'penyebab' => collect(StokOpnameService::PENYEBAB_KURANG)->mapWithKeys(fn ($k) => [$k => round((float) $rs->where('penyebab', $k)->sum('nilai'), 2)])->all()];
        }
        usort($perLokasi, fn ($a, $b) => $a['bersih'] <=> $b['bersih']);

        // per barang (kurang saja, terbesar dulu)
        $perBarang = $rincian->where('penyebab', '!=', 'lebih')->groupBy(fn ($x) => $x->cabang_id . '-' . $x->item_id)->map(function ($rs) {
            $x = $rs->first();
            $per = $rs->groupBy('penyebab')->map(fn ($g, $k) => (StokOpnameService::PENYEBAB[$k]['label'] ?? $k) . ' ' . rtrim(rtrim(number_format((float) $g->sum('qty'), 3, ',', '.'), '0'), ','))->implode('; ');

            return ['kode' => $x->kode_item, 'nama' => $x->nama_item, 'lokasi' => $x->nama_cabang, 'satuan' => $x->satuan, 'qty' => round((float) $rs->sum('qty'), 3),
                'nilai' => round((float) $rs->sum('nilai'), 2), 'kali' => $rs->unique('opname_id')->count(), 'penyebab' => $per];
        })->sortByDesc('nilai')->values()->take(200)->all();

        // riwayat semua opname yang dimulai dalam periode (status apa pun)
        $riwayat = StokOpname::with(['cabang', 'penyetuju'])->withCount('item')->whereIn('cabang_id', $lokasi ?: [0])
            ->where(fn ($q) => $q->whereBetween('created_at', [$mulai, $akhir])->orWhereBetween('disetujui_at', [$mulai, $akhir]))
            ->orderByDesc('id')->get();

        // penyesuaian stok manual (menu Penyesuaian Stok) dalam periode
        $manual = DB::table('transaksi_keuangans as t')->join('kategori_transaksis as k', 'k.id', '=', 't.kategori_id')->join('cabangs as c', 'c.id', '=', 't.cabang_id')
            ->where('t.referensi_type', 'stock_movement')->whereIn('k.kode', ['BEBAN-STOK', 'SO-SALAH', 'KOR-STOK'])->whereNull('t.deleted_at')
            ->whereBetween('t.tanggal_transaksi', [$mulai->toDateString(), $akhir->toDateString()])->whereIn('t.cabang_id', $lokasi ?: [0])
            ->groupBy('c.nama_cabang', 'k.kode', 'k.nama')->orderBy('c.nama_cabang')
            ->selectRaw('c.nama_cabang as lokasi, k.kode, k.nama, COUNT(*) as jumlah, SUM(t.jumlah) as nilai')->get()
            ->map(fn ($x) => ['lokasi' => $x->lokasi, 'kode' => $x->kode, 'nama' => $x->nama, 'jumlah' => (int) $x->jumlah, 'nilai' => round((float) $x->nilai, 2)])->all();

        // Tahap 7c: stok rusak — masih ditahan (sekarang) & tindak lanjut dalam periode
        $ditahan = \App\Models\StokRusak::with(['barang', 'cabang', 'opname'])->where('status', 'ditahan')->whereIn('cabang_id', $lokasi ?: [0])->orderBy('created_at')->get();
        $tindakan = \App\Models\StokRusakTindakan::with(['stokRusak.barang', 'stokRusak.cabang'])
            ->whereHas('stokRusak', fn ($q) => $q->whereIn('cabang_id', $lokasi ?: [0]))->whereBetween('created_at', [$mulai, $akhir])->orderBy('id')->get();
        $rusak = [
            'ditahan' => $ditahan->map(fn ($r) => ['nama' => $r->barang?->nama_item, 'lokasi' => $r->cabang?->nama_cabang, 'qty' => $r->qty_sisa, 'satuan' => $r->satuan,
                'nilai' => $r->nilai_sisa, 'umur' => $r->umur_hari, 'opname' => $r->opname?->nomor])->all(),
            'per_jenis' => collect(\App\Models\StokRusakTindakan::JENIS)->map(fn ($label, $k) => ['label' => $label, 'kali' => $tindakan->where('jenis', $k)->count(),
                'nilai_pokok' => round((float) $tindakan->where('jenis', $k)->sum('nilai_pokok'), 2), 'nilai_pulih' => round((float) $tindakan->where('jenis', $k)->sum('nilai_pulih'), 2)])->all(),
            'nilai_ditahan' => round((float) $ditahan->sum(fn ($r) => $r->nilai_sisa), 2),
            'dipulihkan' => round((float) $tindakan->sum('nilai_pulih'), 2),
        ];

        $nilaiKurang = round((float) $rincian->where('penyebab', '!=', 'lebih')->sum('nilai'), 2);
        $nilaiLebih = round((float) $rincian->where('penyebab', 'lebih')->sum('nilai'), 2);
        $manualRugi = round(collect($manual)->whereIn('kode', ['BEBAN-STOK', 'SO-SALAH'])->sum('nilai'), 2);

        return [
            'per_penyebab' => $perPenyebab, 'per_lokasi' => $perLokasi, 'per_barang' => $perBarang, 'riwayat' => $riwayat, 'manual' => $manual, 'rusak' => $rusak,
            'total' => ['opname' => $opname->count(), 'dipulihkan' => $rusak['dipulihkan'], 'akurasi' => round((float) $opname->avg(fn ($o) => (float) ($o->ringkasan['akurasi'] ?? 0)), 1),
                'nilai_kurang' => $nilaiKurang, 'nilai_lebih' => $nilaiLebih, 'bersih' => round($nilaiLebih - $nilaiKurang, 2), 'manual_rugi' => $manualRugi],
        ];
    }
}
