<?php

namespace App\Http\Controllers;

use App\Enums\StatusProduksi;
use App\Events\AntrianRemoved;
use App\Events\AntrianUpdated;
use App\Models\Cabang;
use App\Models\Karyawan;
use App\Models\Order;
use Illuminate\Http\Request;

class AntrianOperatorController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('antrian.kelola'), 403);

        $cabangId = session('active_cabang_id') ?? auth()->user()->defaultCabangId();
        $cabang   = Cabang::findOrFail($cabangId);

        abort_unless($cabang->antrian_produksi_aktif, 404, 'Sistem antrian tidak aktif untuk cabang ini.');

        $tab        = $request->input('tab', 'aktif');
        $search     = trim($request->input('search', ''));
        $tanggalDari   = $request->input('tanggal_dari', today()->toDateString());
        $tanggalSampai = $request->input('tanggal_sampai', today()->toDateString());

        $query = Order::with('dikerjakanOleh:id,nama_lengkap')
            ->where('cabang_id', $cabangId)
            ->whereNotNull('status_produksi');

        if ($tab === 'aktif') {
            $query->whereDate('tanggal_order', today())
                  ->whereNotIn('status_produksi', [StatusProduksi::Diambil->value]);
        } elseif ($tab === 'selesai_hari_ini') {
            $query->whereDate('tanggal_order', today())
                  ->where('status_produksi', StatusProduksi::Diambil->value);
        } else {
            $query->whereBetween(\DB::raw('DATE(tanggal_order)'), [$tanggalDari, $tanggalSampai]);
        }

        if ($search !== '') {
            $searchNumeric = ltrim($search, '0');
            $query->where(function ($q) use ($search, $searchNumeric) {
                $q->where('nama_pelanggan', 'like', "%{$search}%")
                  ->orWhere('nomor_order', 'like', "%{$search}%")
                  ->orWhere('telepon_pelanggan', 'like', "%{$search}%");
                if ($searchNumeric !== '' && is_numeric($searchNumeric)) {
                    $q->orWhere('nomor_antrian', (int) $searchNumeric);
                }
            });
        }

        $orders = $query->orderByDesc('tanggal_order')->orderBy('nomor_antrian')->get();

        $karyawans = Karyawan::where('cabang_id', $cabangId)
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap']);

        return view('antrian.operator', compact(
            'cabang', 'orders', 'karyawans',
            'tab', 'search', 'tanggalDari', 'tanggalSampai'
        ));
    }

    /** Halaman Mode Produksi (tablet bersama — tidak butuh karyawan terhubung ke akun) */
    public function produksi()
    {
        abort_unless(auth()->user()->can('antrian.kelola'), 403);

        $cabangId = session('active_cabang_id') ?? auth()->user()->defaultCabangId();
        $cabang   = Cabang::findOrFail($cabangId);

        abort_unless($cabang->antrian_produksi_aktif, 404, 'Sistem antrian tidak aktif untuk cabang ini.');

        return view('antrian.produksi', compact('cabang'));
    }

    /** JSON: list antrian aktif (Menunggu + Dikerjakan) hari ini untuk mode produksi */
    public function produksiData()
    {
        abort_unless(auth()->user()->can('antrian.kelola'), 403);

        $cabangId = session('active_cabang_id') ?? auth()->user()->defaultCabangId();
        $now      = now();

        $orders = Order::with('dikerjakanOleh:id,nama_lengkap')
            ->where('cabang_id', $cabangId)
            ->whereNotNull('status_produksi')
            ->whereDate('tanggal_order', today())
            ->whereIn('status_produksi', [
                StatusProduksi::Menunggu->value,
                StatusProduksi::Dikerjakan->value,
            ])
            ->orderBy('nomor_antrian')
            ->get();

        $data = $orders->map(function (Order $order) use ($now) {
            $st = $order->status_produksi;

            // Durasi dihitung server-side agar konsisten dan monotonik naik
            $durasiMenungguMenit = $order->created_at
                ? (int) $order->created_at->diffInMinutes($now)
                : null;

            $durasiKerjaMenit = $order->waktu_mulai_kerja
                ? (int) $order->waktu_mulai_kerja->diffInMinutes($now)
                : null;

            return [
                'id'                   => $order->id,
                'nomor_antrian'        => $order->nomor_antrian,
                'nomor_antrian_pad'    => str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT),
                'nama_pelanggan'       => $order->nama_pelanggan ?: 'Walk-in',
                'berat_daging_kg'      => $order->berat_daging_kg
                    ? number_format((float) $order->berat_daging_kg, 2) . ' kg'
                    : null,
                'catatan_produksi'     => $order->catatan_produksi,
                'status'               => $st->value,
                'dikerjakan_oleh_nama' => $order->dikerjakanOleh?->nama_lengkap,
                'dikerjakan_oleh_id'   => $order->dikerjakan_oleh_id,
                // Durasi server-side — frontend tampilkan apa adanya, tidak kalkulasi ulang
                'durasi_menunggu_menit'=> $durasiMenungguMenit,
                'durasi_kerja_menit'   => $durasiKerjaMenit,
                'waktu_mulai_label'    => $order->waktu_mulai_kerja
                    ? $order->waktu_mulai_kerja->setTimezone('Asia/Jakarta')->format('H:i')
                    : null,
            ];
        });

        return response()->json([
            'orders'      => $data,
            'total_aktif' => $data->count(),
            'dikerjakan'  => $data->where('status', StatusProduksi::Dikerjakan->value)->count(),
            'menunggu'    => $data->where('status', StatusProduksi::Menunggu->value)->count(),
        ]);
    }

    /** JSON: list karyawan aktif di cabang (untuk modal pilih operator di mode produksi) */
    public function karyawanListProduksi()
    {
        abort_unless(auth()->user()->can('antrian.kelola'), 403);

        $cabangId  = session('active_cabang_id') ?? auth()->user()->defaultCabangId();
        $karyawans = Karyawan::where('cabang_id', $cabangId)
            ->where('status', 'aktif')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'jabatan']);

        return response()->json($karyawans);
    }

    public function mulaiKerja(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('antrian.kelola'), 403);

        if ($order->status_produksi !== StatusProduksi::Menunggu) {
            $msg = 'Order tidak dalam status Menunggu.';
            if ($request->expectsJson()) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $data       = $request->validate(['karyawan_id' => 'required|exists:karyawans,id']);
        $karyawanId = (int) $data['karyawan_id'];

        $order->mulaiKerja($karyawanId);

        AntrianUpdated::dispatch($order->fresh(['dikerjakanOleh']), 'mulai_kerja');

        activity()->performedOn($order)
            ->log("Antrian #{$order->nomor_antrian} mulai dikerjakan (karyawan #{$karyawanId})");

        $noPad = str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT);
        $msg   = "Antrian #{$noPad} mulai dikerjakan.";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }

    public function selesai(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('antrian.kelola'), 403);

        if ($order->status_produksi !== StatusProduksi::Dikerjakan) {
            $msg = 'Order tidak dalam status Dikerjakan.';
            if ($request->expectsJson()) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $order->tandaiSelesai();

        AntrianRemoved::dispatch($order->id, $order->cabang_id);

        activity()->performedOn($order)
            ->log("Antrian #{$order->nomor_antrian} selesai dikerjakan");

        $noPad = str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT);
        $msg   = "Antrian #{$noPad} selesai.";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }

    public function simpanRak(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('antrian.kelola'), 403);

        if ($order->status_produksi !== StatusProduksi::Selesai) {
            return back()->with('error', 'Order belum selesai dikerjakan.');
        }

        $lokasi = $request->validate(['lokasi_rak' => 'nullable|string|max:50'])['lokasi_rak'] ?? '';

        $order->simpanDiRak($lokasi);

        AntrianUpdated::dispatch($order->fresh(['dikerjakanOleh']), 'simpan_rak');

        activity()->performedOn($order)
            ->log("Antrian #{$order->nomor_antrian} disimpan di rak" . ($lokasi ? " ({$lokasi})" : ''));

        return back()->with('success', "Antrian #" . str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) . " disimpan di rak.");
    }

    public function diambil(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('antrian.kelola'), 403);

        if (!in_array($order->status_produksi, [StatusProduksi::Selesai, StatusProduksi::Disimpan])) {
            return back()->with('error', 'Order belum selesai dikerjakan.');
        }

        $order->tandaiDiambil();

        AntrianRemoved::dispatch($order->id, $order->cabang_id);

        activity()->performedOn($order)
            ->log("Antrian #{$order->nomor_antrian} sudah diambil pelanggan");

        return back()->with('success', "Antrian #" . str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT) . " sudah diambil.");
    }

    public function ambilAlih(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('antrian.kelola'), 403);

        if ($order->status_produksi !== StatusProduksi::Dikerjakan) {
            $msg = 'Order tidak dalam status Dikerjakan.';
            if ($request->expectsJson()) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $data          = $request->validate(['karyawan_id' => 'required|exists:karyawans,id']);
        $karyawanBaru  = Karyawan::findOrFail((int) $data['karyawan_id']);

        $operatorLamaNama = $order->dikerjakanOleh?->nama_lengkap ?? "ID #{$order->dikerjakan_oleh_id}";
        $operatorLamaId   = $order->dikerjakan_oleh_id;

        $order->update([
            'dikerjakan_oleh_id' => $karyawanBaru->id,
            'waktu_mulai_kerja'  => now(),
        ]);

        AntrianUpdated::dispatch($order->fresh(['dikerjakanOleh']), 'ambil_alih');

        activity()->performedOn($order)
            ->withProperties([
                'dari_karyawan_id'   => $operatorLamaId,
                'dari_karyawan_nama' => $operatorLamaNama,
                'ke_karyawan_id'     => $karyawanBaru->id,
                'ke_karyawan_nama'   => $karyawanBaru->nama_lengkap,
            ])
            ->log("Antrian #{$order->nomor_antrian} diambil alih dari {$operatorLamaNama} oleh {$karyawanBaru->nama_lengkap}");

        $noPad = str_pad($order->nomor_antrian, 3, '0', STR_PAD_LEFT);
        $msg   = "Antrian #{$noPad} diambil alih oleh {$karyawanBaru->nama_lengkap}.";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }
}
