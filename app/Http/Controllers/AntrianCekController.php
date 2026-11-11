<?php

namespace App\Http\Controllers;

use App\Enums\StatusProduksi;
use App\Models\Order;
use Illuminate\Http\Request;

class AntrianCekController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('antrian.lihat'), 403);

        $cabangId = session('active_cabang_id') ?? auth()->user()->defaultCabangId();

        // Default: order aktif hari ini (bukan diambil)
        $orders = Order::with('dikerjakanOleh:id,nama_lengkap')
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal_order', today())
            ->whereNotNull('status_produksi')
            ->orderBy('nomor_antrian')
            ->get();

        return view('antrian.cek', compact('orders', 'cabangId'));
    }

    /** AJAX: cari order berdasarkan nomor antrian / nama / nomor order */
    public function search(Request $request)
    {
        abort_unless(auth()->user()->can('antrian.lihat'), 403);

        $cabangId = session('active_cabang_id') ?? auth()->user()->defaultCabangId();
        $keyword  = trim($request->input('q', ''));
        $tanggal  = $request->input('tanggal') ?: today()->toDateString();

        $query = Order::with('dikerjakanOleh:id,nama_lengkap')
            ->where('cabang_id', $cabangId)
            ->whereDate('tanggal_order', $tanggal)
            ->whereNotNull('status_produksi');

        if ($keyword !== '') {
            $keywordNumeric = ltrim($keyword, '0');
            $query->where(function ($q) use ($keyword, $keywordNumeric) {
                $q->where('nama_pelanggan', 'like', "%{$keyword}%")
                  ->orWhere('nomor_order', 'like', "%{$keyword}%")
                  ->orWhere('telepon_pelanggan', 'like', "%{$keyword}%");
                // Nomor antrian disimpan sebagai integer — strip leading zeros sebelum bandingkan
                if ($keywordNumeric !== '' && is_numeric($keywordNumeric)) {
                    $q->orWhere('nomor_antrian', (int) $keywordNumeric);
                }
            });
        }

        $orders = $query->orderBy('nomor_antrian')->get();

        $tz = 'Asia/Jakarta';
        $fmt = fn ($dt) => $dt ? $dt->setTimezone($tz)->format('H:i') : null;

        $result = $orders->map(fn ($o) => [
            'id'            => $o->id,
            'nomor_antrian' => str_pad($o->nomor_antrian, 3, '0', STR_PAD_LEFT),
            'nomor_order'   => $o->nomor_order,
            'nama_pelanggan'=> $o->nama_pelanggan ?: 'Walk-in',
            'berat'         => $o->berat_daging_kg ? number_format((float)$o->berat_daging_kg, 2) . ' kg' : null,
            'status'        => $o->status_produksi?->value,
            'status_label'  => $o->status_produksi?->label(),
            'lokasi_rak'    => $o->lokasi_rak,
            'operator'      => $o->dikerjakanOleh?->nama_lengkap,
            'mulai'         => $fmt($o->waktu_mulai_kerja),
            'selesai'       => $fmt($o->waktu_selesai_kerja),
            'disimpan_jam'  => $fmt($o->waktu_disimpan),
            'diambil_jam'   => $fmt($o->waktu_diambil),
            'url_detail'    => route('penjualan.show', $o),
            'url_struk'     => route('penjualan.struk', $o),
            'url_diambil'   => route('antrian.cek.diambil', $o),
            'can_diambil'   => in_array($o->status_produksi, [StatusProduksi::Selesai, StatusProduksi::Disimpan]),
        ]);

        return response()->json($result);
    }

    /** Tandai diambil — hanya untuk status selesai/disimpan */
    public function tandaiDiambil(Order $order)
    {
        abort_unless(auth()->user()->can('antrian.lihat'), 403);

        if (!in_array($order->status_produksi, [StatusProduksi::Selesai, StatusProduksi::Disimpan])) {
            return back()->with('error', 'Order belum siap diambil.');
        }

        $order->tandaiDiambil();

        activity()->performedOn($order)
            ->log("Antrian #{$order->nomor_antrian} ditandai diambil via Cek Antrian");

        return back()->with('success', "Antrian #" . str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) . " berhasil ditandai sudah diambil.");
    }

    /** Redirect ke halaman struk untuk cetak ulang */
    public function cetakUlang(Order $order)
    {
        abort_unless(auth()->user()->can('antrian.lihat'), 403);

        return redirect()->route('penjualan.struk', $order);
    }
}
