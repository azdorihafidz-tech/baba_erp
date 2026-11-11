<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Order;
use App\Models\ReturInternal;
use App\Services\ReturInternalService;
use App\Support\CabangAktif;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 4.49 Tahap B3 — Retur Cabang (porting erp-coffeeshop 40bd55e): cabang mengembalikan
 * barang dari kiriman yang sudah diterima; Gudang menekan Terima/Tolak.
 * Filter cabang manual (CLAUDE.md 3.4) lewat CabangAktif.
 */
class ReturInternalController extends Controller
{
    public function __construct(private ReturInternalService $service) {}

    public function index(Request $request)
    {
        abort_unless(auth()->user()->can('retur.view'), 403);

        $arah = $request->input('arah') === 'masuk' ? 'masuk' : 'keluar';
        $status = $request->input('status', '');
        $cabangId = CabangAktif::untukLaporan();

        $retur = ReturInternal::with(['order', 'dariCabang', 'keCabang', 'items.item'])
            ->when($cabangId, fn ($q) => $q->where($arah === 'masuk' ? 'ke_cabang_id' : 'dari_cabang_id', $cabangId))
            ->when(in_array($status, ['menunggu', 'diterima', 'ditolak', 'dibatalkan'], true), fn ($q) => $q->where('status', $status))
            ->orderByRaw("status = 'menunggu' DESC")->orderByDesc('created_at')
            ->paginate(20)->withQueryString();

        return view('retur-cabang.index', [
            'retur'    => $retur,
            'arah'     => $arah,
            'status'   => $status,
            'cabang'   => $cabangId > 0 ? Cabang::find($cabangId) : null,
            'menunggu' => ReturInternalService::jumlahMenunggu(),
        ]);
    }

    public function create(Order $order)
    {
        abort_unless(auth()->user()->can('retur.create'), 403);
        $this->pastikanCabangTujuan($order);
        if ($order->status_kirim !== 'diterima') {
            return redirect()->route('kiriman.show', $order)->with('error', 'Retur hanya bisa dari kiriman yang sudah diterima.');
        }

        $order->load(['cabang', 'outletTujuan', 'kirimanItems.item', 'kirimanItems.itemTujuan', 'kirimanItems.orderItem']);

        return view('retur-cabang.create', ['order' => $order]);
    }

    public function store(Request $request, Order $order)
    {
        abort_unless(auth()->user()->can('retur.create'), 403);
        $this->pastikanCabangTujuan($order);

        $data = $request->validate([
            'qty'          => ['required', 'array'],
            'qty.*'        => ['nullable', 'numeric', 'min:0'],
            'alasan'       => ['required', Rule::in(array_keys(ReturInternal::ALASAN))],
            'uang_kembali' => ['required', 'boolean'],
            'catatan'      => ['nullable', 'string', 'max:500'],
        ], [
            'alasan.required'       => 'Pilih alasan retur.',
            'uang_kembali.required' => 'Pilih apakah uang dikembalikan.',
        ]);

        try {
            $retur = $this->service->buat($order, $data['qty'], $data['alasan'], (bool) $data['uang_kembali'], $data['catatan'] ?? null, auth()->id());
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('retur-cabang.show', $retur)
            ->with('success', "Retur {$retur->kode_retur} dikirim — stok cabang berkurang, menunggu diterima {$order->cabang?->nama_cabang}.");
    }

    public function show(ReturInternal $retur)
    {
        abort_unless(auth()->user()->can('retur.view'), 403);
        abort_unless(CabangAktif::bolehAkses($retur->dari_cabang_id) || CabangAktif::bolehAkses($retur->ke_cabang_id), 403, 'Retur ini bukan dari/untuk cabang Anda.');

        $retur->load(['order', 'dariCabang', 'keCabang', 'items.item', 'dibuatOleh', 'diprosesOleh', 'orderKoreksi']);

        return view('retur-cabang.show', [
            'retur'      => $retur,
            'bisaTerima' => $retur->status === 'menunggu' && auth()->user()->can('retur.terima') && CabangAktif::bolehAkses($retur->ke_cabang_id),
            'bisaBatal'  => $retur->status === 'menunggu' && auth()->user()->can('retur.create') && CabangAktif::bolehAkses($retur->dari_cabang_id),
        ]);
    }

    public function terima(ReturInternal $retur)
    {
        abort_unless(auth()->user()->can('retur.terima'), 403);
        abort_unless(CabangAktif::bolehAkses($retur->ke_cabang_id), 403, 'Hanya pengirim barang yang bisa menerima retur ini.');

        try {
            $retur = $this->service->terima($retur, auth()->id());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        $pesan = $retur->uang_kembali
            ? 'uang Rp ' . number_format((float) $retur->nilai, 0, ',', '.') . ' dikembalikan ke kas cabang.'
            : 'tanpa uang kembali.';

        return back()->with('success', "Retur {$retur->kode_retur} diterima — stok masuk, {$pesan} Barang basi/rusak buang lewat Penyesuaian Stok.");
    }

    public function tolak(Request $request, ReturInternal $retur)
    {
        abort_unless(auth()->user()->can('retur.terima'), 403);
        abort_unless(CabangAktif::bolehAkses($retur->ke_cabang_id), 403, 'Hanya pengirim barang yang bisa menolak retur ini.');

        return $this->batal($request, $retur, 'ditolak');
    }

    public function batalkan(Request $request, ReturInternal $retur)
    {
        abort_unless(auth()->user()->can('retur.create'), 403);
        abort_unless(CabangAktif::bolehAkses($retur->dari_cabang_id), 403, 'Hanya cabang pembuat retur yang bisa membatalkannya.');

        return $this->batal($request, $retur, 'dibatalkan');
    }

    private function batal(Request $request, ReturInternal $retur, string $status)
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:3', 'max:500']], ['alasan.required' => 'Alasan wajib diisi.']);

        try {
            $this->service->tolak($retur, $status, $data['alasan'], auth()->id());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Retur {$retur->kode_retur} {$status} — barang kembali ke stok cabang.");
    }

    private function pastikanCabangTujuan(Order $order): void
    {
        abort_unless($order->isKirimanCabang(), 404);
        abort_unless(CabangAktif::bolehAkses($order->outlet_tujuan_id), 403, 'Hanya cabang penerima kiriman yang bisa membuat retur.');
    }
}
