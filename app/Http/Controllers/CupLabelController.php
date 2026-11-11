<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\CupLabelService;
use App\Support\CabangAktif;
use Illuminate\Http\Request;

/**
 * 4.44 Poin 2 — data & halaman cetak Label Produk (stiker), porting
 * erp-coffeeshop CupLabelController. Default respons JSON (dipakai overlay
 * cetak, termasuk Bluetooth/aplikasi kasir); ?format=html = halaman stiker
 * siap cetak browser.
 */
class CupLabelController extends Controller
{
    public function __construct(private CupLabelService $service)
    {
    }

    public function order(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('label_cup.print'), 403);
        abort_unless(CabangAktif::bolehAkses($order->cabang_id), 403);

        $hasil = $this->service->untukOrder($order);

        if ($request->get('format') === 'html') {
            return view('label-cup.print', ['hasil' => $hasil]);
        }

        return response()->json($hasil + [
            'print_url' => route('label-cup.order', ['order' => $order, 'format' => 'html']),
        ]);
    }
}
