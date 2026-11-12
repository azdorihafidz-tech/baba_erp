<?php

namespace App\Http\Controllers;

use App\Exports\LaporanOnlineExport;
use App\Models\Cabang;
use App\Services\LaporanOnlineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/** ERP BABA Tahap 4 — Toko Online › Laporan Belanja Online (izin toko_online.laporan). */
class LaporanOnlineController extends Controller
{
    public const JENIS = [
        'produk' => 'Per Produk', 'cabang' => 'Per Cabang', 'jabatan' => 'Per Jabatan', 'tim' => 'Per Tim',
        'ro' => 'RO per Member', 'ro_tim' => 'RO per Tim', 'tagihan' => 'Tagihan Member',
    ];

    public function index(Request $request, LaporanOnlineService $svc)
    {
        abort_unless(auth()->user()->can('toko_online.laporan'), 403);
        $jenis = array_key_exists((string) $request->query('jenis'), self::JENIS) ? (string) $request->query('jenis') : 'produk';
        [$dari, $sampai] = $this->periode($request);
        $level = array_key_exists((int) $request->query('level'), LaporanOnlineService::LEVEL_TIM) ? (int) $request->query('level') : 1;
        $gudangList = Cabang::where('tipe', 'gudang_pusat')->orderBy('nama_cabang')->get(['id', 'nama_cabang']);
        $gudangId = $gudangList->firstWhere('id', (int) $request->query('gudang'))?->id;

        $galat = null;
        $baris = collect();
        try {
            $baris = match ($jenis) {
                'produk' => $svc->perProduk($dari, $sampai, $gudangId),
                'cabang', 'jabatan' => $svc->perAkun($dari, $sampai, $jenis, $gudangId),
                'tim' => $svc->perTim($dari, $sampai, $level, $gudangId),
                'ro' => $svc->roPerMember($dari, $sampai),
                'ro_tim' => $svc->roPerTim($dari, $sampai, $level),
                'tagihan' => $svc->tagihan($gudangId),
            };
        } catch (\RuntimeException $e) {
            $galat = $e->getMessage(); // aplikasi member tidak bisa dihubungi (laporan per tim)
        }
        $ringkas = in_array($jenis, ['produk', 'cabang', 'jabatan', 'tim'], true) ? $svc->ringkas($dari, $sampai, $gudangId) : null;

        if ($request->query('ekspor') === 'excel' && ! $galat) {
            [$kolom, $isi] = $this->tabel($jenis, $baris);
            $nama = 'laporan-belanja-online-' . $jenis . '-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.xlsx';

            return Excel::download(new LaporanOnlineExport(self::JENIS[$jenis], $kolom, $isi, auth()->user()->name), $nama);
        }

        return view('toko-online.laporan.index', compact('jenis', 'dari', 'sampai', 'level', 'gudangList', 'gudangId', 'baris', 'ringkas', 'galat'));
    }

    /** @return array{0: Carbon, 1: Carbon} bawaan awal bulan s/d hari ini; tanggal salah / terbalik / > 1 tahun → bawaan. */
    private function periode(Request $request): array
    {
        $baku = [today()->startOfMonth(), today()];
        try {
            $dari = Carbon::createFromFormat('Y-m-d', (string) $request->query('dari'))->startOfDay();
            $sampai = Carbon::createFromFormat('Y-m-d', (string) $request->query('sampai'))->startOfDay();
        } catch (\Throwable) {
            return $baku;
        }
        if (! $dari || ! $sampai || $dari->gt($sampai) || $dari->diffInDays($sampai) > 370) {
            return $baku;
        }

        return [$dari, $sampai];
    }

    private function tabel(string $jenis, $baris): array
    {
        $uang = ['Botol', 'Pesanan', 'Pembeli', 'Omzet', 'HPP', 'Laba kotor', 'Margin %'];
        $angka = fn ($r) => [$r['botol'], $r['pesanan'], $r['pembeli'], $r['omzet'], $r['hpp'], $r['laba'], $r['margin']];

        return match ($jenis) {
            'produk' => [['Produk', ...$uang], $baris->map(fn ($r) => [$r['nama'], ...$angka($r)])->all()],
            'cabang' => [['Cabang member', ...$uang], $baris->map(fn ($r) => [$r['nama'], ...$angka($r)])->all()],
            'jabatan' => [['Jabatan pembeli', ...$uang], $baris->map(fn ($r) => [$r['nama'], ...$angka($r)])->all()],
            'tim' => [['Tim', ...$uang], $baris->map(fn ($r) => [$r['nama'], ...$angka($r)])->all()],
            'ro' => [['No ID', 'No Member', 'Nama', 'Jabatan', 'Cabang', 'RO +', 'RO −', 'RO bersih'],
                $baris->map(fn ($r) => [$r['no_id'], $r['no_member'], $r['nama'], $r['jabatan'], $r['cabang'], $r['tambah'], $r['kurang'], $r['bersih']])->all()],
            'ro_tim' => [['Tim', 'Anggota ber-RO', 'RO +', 'RO −', 'RO bersih'],
                $baris->map(fn ($r) => [$r['nama'], $r['anggota'], $r['tambah'], $r['kurang'], $r['bersih']])->all()],
            'tagihan' => [['Nomor', 'Nama', 'No ID', 'Jabatan', 'Cabang', 'Tanggal pesan', 'Jatuh tempo', 'Terlambat (hari)', 'Sisa tagihan', 'Status'],
                $baris->map(fn ($r) => [$r['nomor'], $r['nama'], $r['no_id'], $r['jabatan'], $r['cabang'], $r['tanggal']?->format('d/m/Y'),
                    $r['jatuh_tempo']?->format('d/m/Y'), $r['telat'], $r['sisa'], $r['status']])->all()],
        };
    }
}
