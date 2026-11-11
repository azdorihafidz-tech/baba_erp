<?php

namespace App\Http\Controllers;

use App\Models\AturanSumber;
use App\Models\AturanTagihan;
use App\Models\MemberAkun;
use App\Models\TokoOnlinePengaturan;
use App\Models\TokoOnlineRekening;
use App\Services\AplikasiMemberClient;
use App\Services\PesananOnlineService;
use App\Services\ProduksiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ERP BABA Tahap 2b — Toko Online › Pengaturan: rekening tujuan transfer per gudang, aturan tagihan
 * (semua / jabatan / akun + batas nominal + tempo), terima otomatis & batas waktu bayar. Izin toko_online.kelola.
 */
class TokoOnlinePengaturanController extends Controller
{
    private function izin(): void
    {
        abort_unless(auth()->user()->can('toko_online.kelola'), 403);
    }

    public function index(AplikasiMemberClient $member, PesananOnlineService $svc)
    {
        $this->izin();
        $gudang = ProduksiService::gudangUntuk();
        try {
            $jabatan = $member->jabatan();
            $galatJabatan = null;
        } catch (\RuntimeException $e) {
            $jabatan = [];
            $galatJabatan = $e->getMessage();
        }
        $aturan = AturanTagihan::with('akun')->orderByDesc('aktif')->orderByRaw("FIELD(cakupan,'akun','jabatan','semua')")->get();

        return view('toko-online.pengaturan', [
            'set' => TokoOnlinePengaturan::ambil(),
            'gudang' => $gudang,
            'rekening' => TokoOnlineRekening::with('cabang')->whereIn('cabang_id', $gudang->pluck('id'))->orderBy('cabang_id')->orderBy('id')->get(),
            'aturan' => $aturan,
            'aturanSumber' => AturanSumber::with('akun')->orderByDesc('aktif')->orderByRaw("FIELD(cakupan,'akun','jabatan','semua')")->get(),
            'terpakai' => $aturan->where('cakupan', 'akun')->mapWithKeys(fn ($a) => [$a->id => $svc->tagihanTerpakai((int) $a->member_id)]),
            'jabatan' => collect($jabatan)->keyBy('id'),
            'galatJabatan' => $galatJabatan,
        ]);
    }

    public function simpanUmum(Request $request)
    {
        $this->izin();
        $d = $request->validate([
            'hari_terima_otomatis' => ['required', 'integer', 'min:0', 'max:60'],
            'jam_batas_bayar' => ['required', 'integer', 'min:0', 'max:720'],
            'hari_batas_retur' => ['required', 'integer', 'min:0', 'max:90'],
        ], [], ['hari_terima_otomatis' => 'Diterima otomatis', 'jam_batas_bayar' => 'Batas waktu bayar', 'hari_batas_retur' => 'Batas pengajuan retur']);
        TokoOnlinePengaturan::ambil()->update($d);

        return back()->with('success', 'Pengaturan pesanan disimpan.');
    }

    public function simpanRekening(Request $request, ?TokoOnlineRekening $rekening = null)
    {
        $this->izin();
        $d = $request->validate([
            'cabang_id' => ['required', Rule::in(ProduksiService::gudangUntuk()->pluck('id')->all())],
            'bank' => ['required', 'string', 'max:50'],
            'nomor' => ['required', 'string', 'max:40', 'regex:/^[0-9 .\-]+$/'],
            'atas_nama' => ['required', 'string', 'max:100'],
        ], ['nomor.regex' => 'Nomor rekening hanya angka.'], ['cabang_id' => 'Gudang', 'nomor' => 'Nomor rekening', 'atas_nama' => 'Atas nama']);
        if ($rekening && $rekening->exists) {
            abort_unless(ProduksiService::gudangUntuk()->pluck('id')->contains($rekening->cabang_id), 404);
            $rekening->update($d);
        } else {
            TokoOnlineRekening::create($d + ['aktif' => true]);
        }

        return back()->with('success', 'Rekening disimpan.');
    }

    public function aktifRekening(TokoOnlineRekening $rekening)
    {
        $this->izin();
        abort_unless(ProduksiService::gudangUntuk()->pluck('id')->contains($rekening->cabang_id), 404);
        $rekening->update(['aktif' => ! $rekening->aktif]);

        return back()->with('success', $rekening->aktif ? 'Rekening ditampilkan lagi ke member.' : 'Rekening disembunyikan dari member.');
    }

    public function simpanAturan(Request $request, AplikasiMemberClient $member)
    {
        $this->izin();
        $d = $request->validate([
            'cakupan' => ['required', Rule::in(array_keys(AturanTagihan::CAKUPAN))],
            'jabatan_id' => ['nullable', 'required_if:cakupan,jabatan', 'integer'],
            'member_id' => ['nullable', 'required_if:cakupan,akun', 'integer'],
            'batas_nominal' => ['required', 'numeric', 'min:1', 'max:10000000000'],
            'tempo_hari' => ['required', 'integer', 'min:1', 'max:180'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], ['jabatan_id.required_if' => 'Pilih jabatan.', 'member_id.required_if' => 'Pilih akun.'],
            ['batas_nominal' => 'Batas nominal', 'tempo_hari' => 'Lama tempo']);
        $d['jabatan_id'] = $d['cakupan'] === 'jabatan' ? (int) $d['jabatan_id'] : null;
        $d['member_id'] = $d['cakupan'] === 'akun' ? (int) $d['member_id'] : null;
        if ($d['cakupan'] === 'akun' && ! MemberAkun::where('member_id', $d['member_id'])->exists()) {
            return back()->withInput()->with('error', 'Akun belum dikenal ERP. Cari akun lewat kotak pencarian lalu pilih.');
        }
        // satu aturan aktif per sasaran: aturan lama untuk sasaran yang sama dinonaktifkan
        AturanTagihan::where('cakupan', $d['cakupan'])->where('aktif', true)
            ->when($d['cakupan'] === 'jabatan', fn ($q) => $q->where('jabatan_id', $d['jabatan_id']))
            ->when($d['cakupan'] === 'akun', fn ($q) => $q->where('member_id', $d['member_id']))
            ->get()->each->update(['aktif' => false]);
        AturanTagihan::create($d + ['aktif' => true, 'created_by' => auth()->id()]);

        return back()->with('success', 'Aturan tagihan disimpan.');
    }

    public function nonaktifAturan(AturanTagihan $aturan)
    {
        $this->izin();
        $aturan->update(['aktif' => false]);

        return back()->with('success', 'Aturan tagihan dinonaktifkan. Tagihan yang sudah berjalan tetap harus dilunasi.');
    }

    /** Tahap 2c — boleh belanja ke gudang / ke upline (semua / jabatan / akun). */
    public function simpanSumber(Request $request)
    {
        $this->izin();
        $d = $request->validate([
            'cakupan' => ['required', Rule::in(array_keys(AturanTagihan::CAKUPAN))],
            'jabatan_id' => ['nullable', 'required_if:cakupan,jabatan', 'integer'],
            'member_id' => ['nullable', 'required_if:cakupan,akun', 'integer'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ], ['jabatan_id.required_if' => 'Pilih jabatan.', 'member_id.required_if' => 'Pilih akun.']);
        $d['boleh_gudang'] = $request->boolean('boleh_gudang');
        $d['boleh_upline'] = $request->boolean('boleh_upline');
        if (! $d['boleh_gudang'] && ! $d['boleh_upline']) {
            return back()->withInput()->with('error', 'Centang minimal satu: Boleh ke gudang atau Boleh ke upline.');
        }
        $d['jabatan_id'] = $d['cakupan'] === 'jabatan' ? (int) $d['jabatan_id'] : null;
        $d['member_id'] = $d['cakupan'] === 'akun' ? (int) $d['member_id'] : null;
        if ($d['cakupan'] === 'akun' && ! MemberAkun::where('member_id', $d['member_id'])->exists()) {
            return back()->withInput()->with('error', 'Akun belum dikenal ERP. Cari akun lewat kotak pencarian lalu pilih.');
        }
        AturanSumber::where('cakupan', $d['cakupan'])->where('aktif', true)
            ->when($d['cakupan'] === 'jabatan', fn ($q) => $q->where('jabatan_id', $d['jabatan_id']))
            ->when($d['cakupan'] === 'akun', fn ($q) => $q->where('member_id', $d['member_id']))
            ->get()->each->update(['aktif' => false]);
        AturanSumber::create($d + ['aktif' => true, 'created_by' => auth()->id()]);

        return back()->with('success', 'Aturan sumber barang disimpan.');
    }

    public function nonaktifSumber(AturanSumber $sumber)
    {
        $this->izin();
        $sumber->update(['aktif' => false]);

        return back()->with('success', 'Aturan sumber barang dinonaktifkan.');
    }
}
