<?php

namespace App\Http\Controllers;

use App\Exceptions\StokOpnameException;
use App\Models\Cabang;
use App\Models\Kas;
use App\Models\StokRusak;
use App\Models\StokRusakTindakan;
use App\Models\Supplier;
use App\Services\StokRusakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** ERP BABA Tahap 7c — Stok & Gudang › Stok Rusak: barang rusak hasil opname yang ditahan + tindak lanjutnya. */
class StokRusakController extends Controller
{
    public const TAB = ['ditahan' => 'Ditahan', 'selesai' => 'Selesai', 'semua' => 'Semua'];

    public function __construct(private StokRusakService $svc)
    {
    }

    private function lokasiTerlihat()
    {
        $u = auth()->user();

        return $u->canAccessAllBranches() ? Cabang::orderBy('nama_cabang')->get() : $u->cabangs()->orderBy('nama_cabang')->get();
    }

    private function izin(?StokRusak $r = null): void
    {
        abort_unless(auth()->user()->can('stok_opname.stok_rusak'), 403);
        if ($r) {
            abort_unless($this->lokasiTerlihat()->contains('id', $r->cabang_id), 404);
        }
    }

    public function index(Request $request)
    {
        $this->izin();
        $u = auth()->user();
        $lokasi = $this->lokasiTerlihat();
        $pilihLokasi = (int) $request->query('lokasi') ?: null;
        if ($pilihLokasi && ! $lokasi->contains('id', $pilihLokasi)) {
            $pilihLokasi = null;
        }
        $tab = array_key_exists($request->query('tab'), self::TAB) ? $request->query('tab') : 'ditahan';
        $dasar = StokRusak::whereIn('cabang_id', $pilihLokasi ? [$pilihLokasi] : $lokasi->pluck('id'));
        $jumlah = ['ditahan' => (clone $dasar)->where('status', 'ditahan')->count(), 'selesai' => (clone $dasar)->where('status', 'selesai')->count(), 'semua' => (clone $dasar)->count()];
        $rusak = (clone $dasar)->when($tab !== 'semua', fn ($q) => $q->where('status', $tab))
            ->with(['barang', 'cabang', 'opname', 'tindakan.user', 'tindakan.supplier', 'tindakan.kas'])->orderByDesc('id')->paginate(30)->withQueryString();
        $nilaiDitahan = (clone $dasar)->where('status', 'ditahan')->get()->sum(fn ($r) => $r->nilai_sisa);
        $boleh = $rusak->getCollection()->mapWithKeys(fn ($r) => [$r->id => $r->status === 'ditahan' && $this->svc->boleh($u, $r)]);
        $kas = Kas::whereIn('cabang_id', $rusak->pluck('cabang_id')->unique())->where('is_active', true)->orderBy('nama_kas')->get(['id', 'cabang_id', 'nama_kas']);
        $supplier = Supplier::orderBy('nama_supplier')->get(['id', 'nama_supplier']);

        return view('stok-opname.rusak', compact('rusak', 'tab', 'jumlah', 'lokasi', 'pilihLokasi', 'nilaiDitahan', 'boleh', 'kas', 'supplier'));
    }

    public function tindak(Request $request, StokRusak $rusak)
    {
        $this->izin($rusak);
        $file = $request->file('foto');
        try {
            $t = $this->svc->tindak(auth()->user(), $rusak, [
                'jenis' => $request->input('jenis'), 'qty' => $request->input('qty'), 'supplier_id' => $request->input('supplier_id'), 'kas_id' => $request->input('kas_id'),
                'nominal' => $request->input('nominal'), 'catatan' => $request->input('catatan'),
                'foto' => $file && $file->isValid() ? file_get_contents($file->getRealPath()) : null,
            ]);
        } catch (StokOpnameException $e) {
            return back()->with('error', e($e->getMessage()))->withInput();
        }

        return back()->with('success', e(($rusak->barang?->nama_item ?? 'Barang') . ': ' . $t->jenis_label . ' tercatat.'));
    }

    public function foto(StokRusakTindakan $tindakan)
    {
        $this->izin($tindakan->stokRusak);
        abort_unless($tindakan->foto && Storage::disk('local')->exists($tindakan->foto), 404);

        return Storage::disk('local')->response($tindakan->foto, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Berita acara pemusnahan (PDF) untuk satu tindakan "musnahkan". */
    public function beritaAcaraMusnah(StokRusakTindakan $tindakan)
    {
        $this->izin($tindakan->stokRusak);
        abort_unless($tindakan->jenis === 'musnahkan', 404);
        $tindakan->load(['stokRusak.barang', 'stokRusak.cabang', 'stokRusak.opname', 'user']);
        $isi = $tindakan->foto && Storage::disk('local')->exists($tindakan->foto) ? Storage::disk('local')->get($tindakan->foto) : null;
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('laporan.pdf.stok-rusak-musnah', [
            't' => $tindakan, 'fotoData' => $isi ? 'data:' . (new \finfo(FILEINFO_MIME_TYPE))->buffer($isi) . ';base64,' . base64_encode($isi) : null,
            'judulLaporan' => 'Berita Acara Pemusnahan Barang', 'filterInfo' => ['Lokasi' => $tindakan->stokRusak->cabang?->nama_cabang, 'Tanggal' => $tindakan->created_at?->format('d/m/Y')],
            'footerDicetak' => 'Dicetak oleh: ' . auth()->user()->name . ' pada ' . now()->translatedFormat('d F Y, H:i') . ' WIB',
        ]);

        return $pdf->download('pemusnahan-' . $tindakan->id . '.pdf');
    }
}
