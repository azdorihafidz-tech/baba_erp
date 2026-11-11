<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Order;

class AntrianDisplayController extends Controller
{
    /** Halaman TV display — publik, tanpa autentikasi */
    public function show(Cabang $cabang)
    {
        abort_unless($cabang->is_active && $cabang->antrian_produksi_aktif, 404,
            'Sistem antrian tidak aktif untuk cabang ini.');

        // 4.44 — nama & logo usaha dari $brand (App\Support\Branding)
        return view('antrian.display', compact('cabang'));
    }

    /** API JSON untuk polling AJAX dari display TV */
    public function data(Cabang $cabang)
    {
        $today = today();
        $tz    = 'Asia/Jakarta';

        $orders = Order::with('dikerjakanOleh:id,nama_lengkap')
            ->where('cabang_id', $cabang->id)
            ->whereDate('tanggal_order', $today)
            ->whereNotNull('status_produksi')
            ->whereIn('status_produksi', ['menunggu', 'dikerjakan', 'selesai', 'disimpan'])
            ->orderBy('nomor_antrian')
            ->get([
                'id', 'nomor_antrian', 'nomor_order', 'nama_pelanggan',
                'status_produksi', 'berat_daging_kg', 'lokasi_rak',
                'dikerjakan_oleh_id',
                'waktu_mulai_kerja', 'waktu_selesai_kerja',
                'waktu_disimpan',
            ]);

        $fmt = fn ($dt) => $dt
            ? $dt->setTimezone($tz)->format('H:i')
            : null;

        $map = fn ($row) => [
            'nomor'          => str_pad($row->nomor_antrian, 3, '0', STR_PAD_LEFT),
            'nomor_order'    => $row->nomor_order,
            'pelanggan'      => $row->nama_pelanggan ?: 'Walk-in',
            'berat'          => $row->berat_daging_kg
                                    ? number_format((float) $row->berat_daging_kg, 2) . ' kg'
                                    : null,
            'lokasi_rak'     => $row->lokasi_rak,
            'operator'       => $row->dikerjakanOleh?->nama_lengkap,
            'mulai'          => $fmt($row->waktu_mulai_kerja),
            'selesai'        => $fmt($row->waktu_selesai_kerja),
            'disimpan_jam'   => $fmt($row->waktu_disimpan),
        ];

        return response()->json([
            'menunggu'   => $orders->where('status_produksi', 'menunggu')->values()->map($map)->values(),
            'dikerjakan' => $orders->where('status_produksi', 'dikerjakan')->values()->map($map)->values(),
            'selesai'    => $orders->where('status_produksi', 'selesai')->values()->map($map)->values(),
            'disimpan'   => $orders->where('status_produksi', 'disimpan')->values()->map($map)->values(),
            'tanggal'    => now()->setTimezone($tz)->isoFormat('dddd, D MMMM Y'),
        ]);
    }
}
