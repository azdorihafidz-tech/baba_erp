<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\MemberAkun;
use App\Models\ProdukOnline;
use App\Models\ProdukOnlineHarga;
use App\Services\AplikasiMemberClient;
use App\Services\ProduksiService;
use App\Services\StokService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * ERP BABA Tahap 2a — Toko Online › Produk Online: barang (parfum botol milik gudang) yang dijual di
 * menu Belanja aplikasi member + daftar harga (tetap, gudang, per jabatan, khusus akun). Izin toko_online.kelola.
 */
class ProdukOnlineController extends Controller
{
    private function izin(): void
    {
        abort_unless(auth()->user()->can('toko_online.kelola'), 403);
    }

    private function gudangIds()
    {
        return ProduksiService::gudangUntuk()->pluck('id');
    }

    /** Barang milik gudang yang bisa dijual online (bertipe bahan baku/kemasan, satuan pcs, aktif). */
    private function barangBisaDijual(?ProdukOnline $kecuali = null)
    {
        $sudah = ProdukOnline::when($kecuali, fn ($q) => $q->where('id', '!=', $kecuali->id))->pluck('item_id');

        return Item::with('category')->whereIn('cabang_id', $this->gudangIds())->aktif()
            ->whereIn('tipe', ProduksiService::TIPE_BARANG)->whereNotIn('id', $sudah)
            ->orderByRaw("CASE WHEN satuan = 'pcs' THEN 0 ELSE 1 END")->orderBy('nama_item')->get();
    }

    private function pastikanMilikSaya(ProdukOnline $p): void
    {
        abort_unless($p->item && $this->gudangIds()->contains($p->item->cabang_id), 404);
    }

    public function index(StokService $stok)
    {
        $this->izin();
        $produk = ProdukOnline::with(['item.cabang', 'harga'])
            ->whereHas('item', fn ($q) => $q->whereIn('cabang_id', $this->gudangIds()))
            ->orderBy('urutan')->orderBy('nama_tampil')->get();

        return view('toko-online.produk.index', [
            'produk' => $produk,
            'stok'   => $produk->mapWithKeys(fn ($p) => [$p->id => $stok->getStok($p->item_id, (int) $p->item->cabang_id)]),
        ]);
    }

    public function create()
    {
        $this->izin();

        return view('toko-online.produk.form', ['produk' => null, 'barang' => $this->barangBisaDijual(), 'jabatan' => [], 'galatJabatan' => null, 'akunKhusus' => collect()]);
    }

    public function store(Request $request)
    {
        $this->izin();
        $data = $this->validasi($request, null);
        $p = DB::transaction(function () use ($data, $request) {
            $p = ProdukOnline::create($this->isiProduk($data, $request) + ['created_by' => auth()->id()]);
            $this->simpanHarga($p, $data);

            return $p;
        });

        return redirect()->route('toko-online.produk.edit', $p)->with('success', "Produk online {$p->nama_tampil} ditambahkan. Atur harga per jabatan di bawah bila perlu.");
    }

    public function edit(ProdukOnline $produk, AplikasiMemberClient $member)
    {
        $this->izin();
        $this->pastikanMilikSaya($produk);
        $produk->load(['item', 'harga']);
        [$jabatan, $galat] = $this->ambilJabatan($member);

        $akunIds = $produk->harga->where('jenis', 'akun')->pluck('member_id');

        return view('toko-online.produk.form', [
            'produk'       => $produk,
            'barang'       => $this->barangBisaDijual($produk),
            'jabatan'      => $jabatan,
            'galatJabatan' => $galat,
            'akunKhusus'   => MemberAkun::whereIn('member_id', $akunIds)->get()->keyBy('member_id'),
        ]);
    }

    public function update(Request $request, ProdukOnline $produk)
    {
        $this->izin();
        $this->pastikanMilikSaya($produk);
        $data = $this->validasi($request, $produk);
        DB::transaction(function () use ($data, $request, $produk) {
            $produk->update($this->isiProduk($data, $request, $produk));
            $this->simpanHarga($produk, $data);
        });

        return redirect()->route('toko-online.produk.edit', $produk)->with('success', "Produk online {$produk->nama_tampil} disimpan.");
    }

    /** Cari akun member (untuk harga khusus akun) lewat aplikasi member. */
    public function cariAkun(Request $request, AplikasiMemberClient $member)
    {
        $this->izin();
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['ok' => true, 'data' => []]); // sama dengan layar: cari mulai 2 huruf
        }
        try {
            $data = $member->cariAkun($q);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'pesan' => $e->getMessage()], 502);
        }
        // simpan salinan identitas supaya nama tampil di daftar harga khusus
        foreach ($data as $a) {
            MemberAkun::updateOrCreate(['member_id' => $a['id']], [
                'nama' => $a['nama'], 'no_id' => $a['no_id'], 'no_member' => $a['no_member'],
                'jabatan_id' => $a['jabatan_id'], 'jabatan' => $a['jabatan'], 'cabang' => $a['cabang'],
            ]);
        }

        return response()->json(['ok' => true, 'data' => $data]);
    }

    /** @return array{0: array, 1: ?string} */
    private function ambilJabatan(AplikasiMemberClient $member): array
    {
        try {
            return [$member->jabatan(), null];
        } catch (\RuntimeException $e) {
            return [[], $e->getMessage()];
        }
    }

    private function validasi(Request $request, ?ProdukOnline $p): array
    {
        $boleh = $this->barangBisaDijual($p)->pluck('id')->all();

        return $request->validate([
            'item_id'          => ['required', 'integer', Rule::in($boleh)],
            'nama_tampil'      => ['required', 'string', 'max:150'],
            'kategori'         => ['nullable', 'string', 'max:50'],
            'deskripsi'        => ['nullable', 'string', 'max:2000'],
            'urutan'           => ['nullable', 'integer', 'min:0', 'max:9999'],
            'foto'             => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'harga_tetap'      => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'harga_gudang'     => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'harga_jabatan'    => ['nullable', 'array'],
            'harga_jabatan.*'  => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'harga_akun'       => ['nullable', 'array'],
            'harga_akun.*'     => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ], [
            'item_id.in'           => 'Pilih barang milik gudang Anda yang belum dijual online.',
            'nama_tampil.required' => 'Isi nama yang tampil di aplikasi member.',
            'foto.image'           => 'Foto harus gambar JPG, PNG, atau WEBP.',
            'foto.max'             => 'Foto maksimal 3 MB.',
        ]);
    }

    private function isiProduk(array $data, Request $request, ?ProdukOnline $p = null): array
    {
        $isi = [
            'item_id'     => (int) $data['item_id'],
            'nama_tampil' => trim($data['nama_tampil']),
            'kategori'    => $data['kategori'] ?? null,
            'deskripsi'   => $data['deskripsi'] ?? null,
            'urutan'      => (int) ($data['urutan'] ?? 0),
            'aktif'       => $request->boolean('aktif'),
        ];
        if ($request->hasFile('foto')) {
            $file = $request->file('foto');
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$file->getMimeType()] ?? null; // dari isi file, bukan nama
            abort_unless($ext, 422, 'Jenis foto tidak dikenal.');
            $folder = config('baba.folder_foto_produk');
            $nama = 'produk-' . Str::random(20) . '.' . $ext;
            $file->move(public_path($folder), $nama);
            if ($p?->foto && str_starts_with($p->foto, $folder . '/') && is_file(public_path($p->foto))) {
                @unlink(public_path($p->foto));
            }
            $isi['foto'] = $folder . '/' . $nama;
        }

        return $isi;
    }

    /** Tulis ulang harga: kosong = tidak berlaku. */
    private function simpanHarga(ProdukOnline $p, array $data): void
    {
        $target = [];
        foreach (['tetap' => 'harga_tetap', 'gudang' => 'harga_gudang'] as $jenis => $kolom) {
            if (isset($data[$kolom]) && $data[$kolom] !== '' && $data[$kolom] !== null) {
                $target["{$jenis}|-|-"] = (float) $data[$kolom];
            }
        }
        foreach ($data['harga_jabatan'] ?? [] as $jabatanId => $h) {
            if ($h !== null && $h !== '' && ctype_digit((string) $jabatanId)) {
                $target["jabatan|{$jabatanId}|-"] = (float) $h;
            }
        }
        foreach ($data['harga_akun'] ?? [] as $memberId => $h) {
            if ($h !== null && $h !== '' && ctype_digit((string) $memberId)) {
                $target["akun|-|{$memberId}"] = (float) $h;
            }
        }

        $ada = $p->harga()->get()->keyBy(fn ($h) => $h->jenis . '|' . ($h->jabatan_id ?? '-') . '|' . ($h->member_id ?? '-'));
        foreach ($ada as $kunci => $h) {
            if (! array_key_exists($kunci, $target)) {
                $h->delete(); // tercatat di audit log
            } elseif ((float) $h->harga !== $target[$kunci]) {
                $h->update(['harga' => $target[$kunci]]);
            }
        }
        foreach ($target as $kunci => $harga) {
            if (! $ada->has($kunci)) {
                [$jenis, $jab, $mem] = explode('|', $kunci);
                ProdukOnlineHarga::create([
                    'produk_online_id' => $p->id, 'jenis' => $jenis, 'harga' => $harga,
                    'jabatan_id' => $jab === '-' ? null : (int) $jab, 'member_id' => $mem === '-' ? null : (int) $mem,
                ]);
            }
        }
    }
}
