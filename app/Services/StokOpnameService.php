<?php

namespace App\Services;

use App\Enums\KategoriTransaksi as KategoriEnum;
use App\Enums\TipeTransaksiKeuangan;
use App\Exceptions\StokOpnameException;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\KategoriTransaksi;
use App\Models\PengaturanUmum;
use App\Models\StokOpname;
use App\Models\StokOpnameItem;
use App\Models\StokOpnameLog;
use App\Models\StokOpnameRincian;
use App\Models\TransaksiKeuangan;
use App\Models\User;
use App\Notifications\StokOpnameNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ERP BABA Tahap 7 — stok opname (rancangan ../_rancangan/RANCANGAN-STOK-OPNAME.md). SATU-SATUNYA tempat aturan opname.
 *
 * Alur: mulai → hitung (hitung buta, scan) → periksa selisih + rincikan penyebab + foto → ajukan → setujui / tolak (kembali ke hitung).
 * Disetujui = SELISIH (fisik − stok sistem saat barang dihitung) diterapkan lewat StokService::selisihOpname + transaksi keuangan
 * per penyebab (kas_id null: barang rusak/hilang tidak memotong kas, laba yang turun). Toko mitra (kemitraan / toko_member):
 * hanya orang tokonya sendiri yang boleh mengubah & menyetujui; Owner BABA hanya melihat; keuangannya di luar BABA (KeuanganBaba::saring).
 */
class StokOpnameService
{
    /** Penyebab selisih → pencatatan keuangan. 'lebih' khusus selisih positif; selain itu selisih negatif. */
    public const PENYEBAB = [
        'rusak'       => ['label' => 'Rusak / pecah / bocor', 'kategori' => 'SO-RUSAK', 'nama' => 'Kerugian Stok - Rusak', 'coa' => '6-1402', 'tipe' => 'pengeluaran'],
        'hilang'      => ['label' => 'Hilang', 'kategori' => 'SO-HILANG', 'nama' => 'Kerugian Stok - Hilang', 'coa' => '6-1403', 'tipe' => 'pengeluaran'],
        'susut'       => ['label' => 'Susut / menguap', 'kategori' => 'SO-SUSUT', 'nama' => 'Penyusutan Stok', 'coa' => '6-1404', 'tipe' => 'pengeluaran'],
        'tester'      => ['label' => 'Tester / promosi', 'kategori' => 'SO-TESTER', 'nama' => 'Beban Promosi (Tester)', 'coa' => '6-1304', 'tipe' => 'pengeluaran'],
        'kedaluwarsa' => ['label' => 'Kedaluwarsa', 'kategori' => 'SO-KEDALUWARSA', 'nama' => 'Kerugian Stok - Kedaluwarsa', 'coa' => '6-1405', 'tipe' => 'pengeluaran'],
        'salah_catat' => ['label' => 'Salah catat', 'kategori' => 'SO-SALAH', 'nama' => 'Selisih Persediaan', 'coa' => '6-1406', 'tipe' => 'pengeluaran'],
        'lebih'       => ['label' => 'Lebih (fisik > sistem)', 'kategori' => 'KOR-STOK', 'nama' => 'Koreksi Stok Masuk', 'coa' => '4-1199', 'tipe' => 'pemasukan'],
    ];

    public const PENYEBAB_KURANG = ['rusak', 'hilang', 'susut', 'tester', 'kedaluwarsa', 'salah_catat'];

    /** Satuan yang boleh desimal; satuan lain (pcs, botol, …) harus bilangan bulat. */
    public const SATUAN_DESIMAL = ['ml', 'liter', 'l', 'cc', 'gram', 'gr', 'g', 'kg'];

    public const FOLDER_FOTO = 'stok-opname';

    public const MAKS_FOTO = 4 * 1024 * 1024;

    public function __construct(private StokService $stok)
    {
    }

    // ───────────────────────────── hak ─────────────────────────────

    public static function lokasiMitra(?Cabang $c): bool
    {
        return in_array($c?->jenis_toko, ['kemitraan', 'toko_member'], true);
    }

    private function aksesLokasi(User $u, int $cabangId): bool
    {
        return $u->canAccessAllBranches() || $u->cabangs()->where('cabangs.id', $cabangId)->exists();
    }

    /** Boleh mengubah data opname lokasi ini: toko mitra hanya orang tokonya sendiri (bukan akun semua-cabang). */
    public function bolehUbahLokasi(User $u, Cabang $c): bool
    {
        if (self::lokasiMitra($c)) {
            return ! $u->canAccessAllBranches() && $u->cabangs()->where('cabangs.id', $c->id)->exists();
        }

        return $this->aksesLokasi($u, $c->id);
    }

    public function bolehLihat(User $u, StokOpname $o): bool
    {
        return $u->can('stok_opname.lihat') && $this->aksesLokasi($u, (int) $o->cabang_id);
    }

    /** Melihat stok sistem & selisih: tidak hitung buta, atau pemeriksa / penyetuju, atau opname sudah ditutup. */
    public function lihatSistem(User $u, StokOpname $o): bool
    {
        return ! $o->hitung_buta || ! $o->berjalan || $u->can('stok_opname.ajukan') || $u->can('stok_opname.setujui');
    }

    public function penghitungIds(StokOpname $o): array
    {
        return StokOpnameItem::where('stok_opname_id', $o->id)->whereNotNull('dihitung_oleh')->distinct()->pluck('dihitung_oleh')->map(fn ($i) => (int) $i)->all();
    }

    /** Boleh melakukan aksi: hitung | periksa (rincian, minta hitung ulang, ajukan) | setujui (juga tolak) | batal. */
    public function boleh(User $u, StokOpname $o, string $aksi): bool
    {
        $o->loadMissing('cabang');
        if (! $o->cabang || ! $this->bolehUbahLokasi($u, $o->cabang)) {
            return false;
        }

        return match ($aksi) {
            'hitung'  => $o->status === 'hitung' && $u->can('stok_opname.hitung'),
            'periksa' => $o->status === 'hitung' && $u->can('stok_opname.ajukan'),
            'batal'   => $o->berjalan && $u->can('stok_opname.batal'),
            'setujui' => $o->status === 'diajukan' && $u->can('stok_opname.setujui')
                // penghitung tidak menyetujui hitungannya sendiri — kecuali Owner & pemilik toko mitra (stoknya sendiri)
                && ($u->role === \App\Enums\RoleUser::Owner || $o->milik_mitra || ! in_array((int) $u->id, $this->penghitungIds($o), true)),
            default   => false,
        };
    }

    /** Lokasi yang boleh dimulai opnamenya oleh user. */
    public function lokasiUntuk(User $u): Collection
    {
        if (! $u->can('stok_opname.hitung')) {
            return collect();
        }

        return Cabang::where('is_active', true)->orderBy('nama_cabang')->get()->filter(fn (Cabang $c) => $this->bolehUbahLokasi($u, $c))->values();
    }

    // ───────────────────────────── mulai ─────────────────────────────

    /**
     * Barang yang bisa dihitung di lokasi: barang ber-stok (track_stok) milik lokasi yang aktif, plus barang yang masih punya
     * stok di lokasi itu (nonaktif, atau data lama tanpa pemilik lokasi — yang tanpa stok di sini tidak ikut supaya daftar tidak penuh barang lokasi lain).
     */
    public function barangLokasi(Cabang $c): \Illuminate\Database\Eloquent\Builder
    {
        $adaStok = fn ($s) => $s->from('stocks')->whereColumn('stocks.item_id', 'items.id')->where('stocks.lokasi_id', $c->id)->where('stocks.qty', '>', 0);

        return Item::query()->where('track_stok', true)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('items.cabang_id', $c->id)->where('items.is_active', true))->orWhereExists($adaStok));
    }

    /**
     * @param array{cakupan:string, kategori?:array, barang?:array, hitung_buta?:bool, catatan?:string} $d
     */
    public function mulai(User $u, Cabang $c, array $d): StokOpname
    {
        if (! $u->can('stok_opname.hitung') || ! $this->bolehUbahLokasi($u, $c)) {
            throw new StokOpnameException(self::lokasiMitra($c) ? 'Opname toko mitra hanya bisa dimulai oleh orang toko itu sendiri.' : 'Anda tidak punya akses opname di lokasi ini.');
        }
        $cakupan = array_key_exists($d['cakupan'] ?? '', StokOpname::CAKUPAN) ? $d['cakupan'] : 'semua';
        $q = $this->barangLokasi($c);
        $ket = null;
        if ($cakupan === 'kategori') {
            $kat = array_values(array_filter(array_map('intval', (array) ($d['kategori'] ?? []))));
            if (! $kat) {
                throw new StokOpnameException('Pilih minimal satu kategori.');
            }
            $q->whereIn('item_category_id', $kat);
            $ket = \App\Models\ItemCategory::whereIn('id', $kat)->orderBy('nama_kategori')->pluck('nama_kategori')->implode(', ');
        } elseif ($cakupan === 'barang') {
            $ids = array_values(array_filter(array_map('intval', (array) ($d['barang'] ?? []))));
            if (! $ids) {
                throw new StokOpnameException('Pilih minimal satu barang.');
            }
            $q->whereIn('items.id', $ids);
        }
        $barang = $q->orderBy('nama_item')->get(['items.id', 'items.nama_item', 'items.satuan']);
        if ($barang->isEmpty()) {
            throw new StokOpnameException('Tidak ada barang ber-stok dalam cakupan ini.');
        }
        if ($cakupan === 'barang') {
            $ket = $barang->count() . ' barang: ' . Str::limit($barang->pluck('nama_item')->implode(', '), 450);
        }

        return DB::transaction(function () use ($u, $c, $d, $cakupan, $ket, $barang) {
            // satu barang hanya boleh ada di satu opname berjalan pada lokasi yang sama
            $bentrok = StokOpnameItem::join('stok_opname', 'stok_opname.id', '=', 'stok_opname_item.stok_opname_id')
                ->where('stok_opname.cabang_id', $c->id)->whereIn('stok_opname.status', ['hitung', 'diajukan'])
                ->whereIn('stok_opname_item.item_id', $barang->pluck('id'))->lockForUpdate()
                ->select('stok_opname.nomor')->distinct()->pluck('nomor');
            if ($bentrok->isNotEmpty()) {
                throw new StokOpnameException('Sebagian barang sedang dihitung di opname ' . $bentrok->implode(', ') . '. Selesaikan atau batalkan opname itu dulu.');
            }
            $prefix = 'SO-' . now()->format('Ym') . '-';
            $akhir = StokOpname::where('nomor', 'like', $prefix . '%')->lockForUpdate()->max(DB::raw("CAST(SUBSTRING(nomor, 11) AS UNSIGNED)"));
            $o = StokOpname::create([
                'nomor' => $prefix . str_pad((string) (((int) $akhir) + 1), 4, '0', STR_PAD_LEFT), 'cabang_id' => $c->id, 'cakupan' => $cakupan,
                'cakupan_ket' => $ket, 'status' => 'hitung', 'dibuat_oleh' => $u->id,
                'hitung_buta' => array_key_exists('hitung_buta', $d) ? (bool) $d['hitung_buta'] : (bool) (PengaturanUmum::getSetting()->opname_hitung_buta ?? true),
                'catatan' => isset($d['catatan']) ? Str::limit(trim((string) $d['catatan']), 500, '') : null,
            ]);
            $now = now();
            StokOpnameItem::insert($barang->map(fn ($b) => ['stok_opname_id' => $o->id, 'item_id' => $b->id, 'satuan' => $b->satuan,
                'created_at' => $now, 'updated_at' => $now])->all());
            $this->log($o, 'mulai', $u, $o->cabang?->nama_cabang . ' · ' . $barang->count() . ' barang');

            return $o;
        });
    }

    // ───────────────────────────── hitung ─────────────────────────────

    public static function bolehDesimal(?string $satuan): bool
    {
        return in_array(strtolower(trim((string) $satuan)), self::SATUAN_DESIMAL, true);
    }

    /** Cari baris opname lewat barcode / kode barang (hasil scan). */
    public function cariBaris(StokOpname $o, string $kode): ?StokOpnameItem
    {
        $kode = trim($kode);
        if ($kode === '') {
            return null;
        }

        // Barcode melekat pada parfum botol gudang & produk kasir JL-<kode> (Tahap 6a); stok toko memakai barang padanan
        // ber-Kode Item sama tanpa barcode → cocokkan juga lewat kode barang pemilik barcode itu.
        $kodeItem = Item::where('barcode', $kode)->pluck('kode_item')->map(fn ($k) => preg_replace('/^JL-/', '', (string) $k))->push($kode)->unique()->all();

        return StokOpnameItem::where('stok_opname_id', $o->id)
            ->whereHas('barang', fn ($q) => $q->where('barcode', $kode)->orWhereIn('kode_item', $kodeItem))->first();
    }

    /**
     * Catat hitungan fisik. $tambah = true → ditambahkan ke hitungan sekarang (scan barcode / tombol +1).
     * Stok sistem dicatat ulang setiap kali hitungan berubah (= keadaan saat barang dihitung).
     */
    public function hitung(User $u, StokOpname $o, StokOpnameItem $row, float $qty, bool $tambah = false): StokOpnameItem
    {
        if (! $this->boleh($u, $o, 'hitung')) {
            throw new StokOpnameException($o->status !== 'hitung' ? 'Opname ini sudah ' . strtolower($o->status_label) . '; hitungan tidak bisa diubah.' : 'Anda tidak punya izin menghitung di opname ini.');
        }
        abort_unless((int) $row->stok_opname_id === (int) $o->id, 404);

        return DB::transaction(function () use ($u, $o, $row, $qty, $tambah) {
            $row = StokOpnameItem::whereKey($row->id)->lockForUpdate()->firstOrFail();
            $baru = $tambah ? (float) ($row->qty_fisik ?? 0) + $qty : $qty;
            $baru = round($baru, 3);
            if ($baru < 0 || $baru > 9999999) {
                throw new StokOpnameException('Jumlah fisik harus antara 0 dan 9.999.999.');
            }
            $nama = $row->barang?->nama_item ?? 'Barang';
            if (! self::bolehDesimal($row->satuan) && abs($baru - round($baru)) > 0.0001) {
                throw new StokOpnameException("{$nama} dihitung per {$row->satuan}: isi bilangan bulat.");
            }
            $selisihLama = $row->selisih;
            $pertama = $row->qty_fisik === null;
            $row->fill([
                'qty_sistem' => $this->stok->getStok((int) $row->item_id, (int) $o->cabang_id),
                'qty_fisik' => $baru,
                'qty_fisik_awal' => $pertama ? $baru : $row->qty_fisik_awal,
                'hitung_ke' => $pertama ? 1 : ($tambah ? max(1, $row->hitung_ke) : $row->hitung_ke + 1),
                'minta_hitung_ulang' => false,
                'dihitung_oleh' => $u->id,
                'dihitung_at' => now(),
            ])->save();
            if ($selisihLama !== null && abs((float) $selisihLama - (float) $row->selisih) > 0.0005) {
                $this->hapusRincian($row); // selisih berubah → rincian penyebab lama tidak berlaku lagi
            }

            return $row->fresh(['barang']);
        });
    }

    public function mintaHitungUlang(User $u, StokOpname $o, array $itemIds): int
    {
        if (! $this->boleh($u, $o, 'periksa')) {
            throw new StokOpnameException('Anda tidak bisa meminta hitung ulang di opname ini.');
        }
        $rows = StokOpnameItem::where('stok_opname_id', $o->id)->whereIn('id', array_map('intval', $itemIds))->whereNotNull('qty_fisik')->get();
        if ($rows->isEmpty()) {
            throw new StokOpnameException('Pilih barang yang sudah dihitung.');
        }
        StokOpnameItem::whereIn('id', $rows->pluck('id'))->update(['minta_hitung_ulang' => true]);
        $this->log($o, 'hitung_ulang', $u, $rows->count() . ' barang: ' . Str::limit($rows->map(fn ($r) => $r->barang?->nama_item)->implode(', '), 400));

        return $rows->count();
    }

    // ───────────────────────────── rincian penyebab ─────────────────────────────

    /**
     * Ganti rincian penyebab satu barang yang selisihnya kurang. $baris: [{penyebab, qty, catatan?, foto? (biner), foto_dari? (id rincian lama)}].
     * Jumlah rincian wajib = selisih. Foto dicek lengkap saat diajukan.
     */
    public function simpanRincian(User $u, StokOpname $o, StokOpnameItem $row, array $baris): void
    {
        if (! $this->boleh($u, $o, 'periksa')) {
            throw new StokOpnameException('Anda tidak bisa mengisi penyebab di opname ini.');
        }
        abort_unless((int) $row->stok_opname_id === (int) $o->id, 404);
        $selisih = $row->selisih;
        if ($selisih === null || $selisih >= 0) {
            throw new StokOpnameException('Penyebab hanya diisi untuk barang yang fisiknya kurang dari stok sistem.');
        }
        $lama = $row->rincian()->get()->keyBy('id');
        $siap = [];
        $total = 0.0;
        foreach ($baris as $b) {
            $penyebab = (string) ($b['penyebab'] ?? '');
            $qty = round((float) ($b['qty'] ?? 0), 3);
            if ($qty <= 0) {
                continue;
            }
            if (! in_array($penyebab, self::PENYEBAB_KURANG, true)) {
                throw new StokOpnameException('Pilih penyebab untuk setiap jumlah.');
            }
            if (! self::bolehDesimal($row->satuan) && abs($qty - round($qty)) > 0.0001) {
                throw new StokOpnameException("Jumlah per penyebab dihitung per {$row->satuan}: isi bilangan bulat.");
            }
            $foto = null;
            if (! empty($b['foto'])) {
                $foto = $this->simpanFoto($o, (string) $b['foto']);
            } elseif (! empty($b['foto_dari']) && ($l = $lama->get((int) $b['foto_dari'])) && $l->foto) {
                $foto = $l->foto;
            }
            $total += $qty;
            $siap[] = ['penyebab' => $penyebab, 'qty' => $qty, 'foto' => $foto, 'catatan' => isset($b['catatan']) ? Str::limit(trim((string) $b['catatan']), 255, '') : null];
        }
        if (! $siap) {
            throw new StokOpnameException('Isi minimal satu penyebab.');
        }
        if (abs(round($total, 3) - abs($selisih)) > 0.0005) {
            foreach ($siap as $s) { // foto baru yang tidak jadi dipakai
                if ($s['foto'] && ! $lama->contains('foto', $s['foto'])) {
                    Storage::disk('local')->delete($s['foto']);
                }
            }
            throw new StokOpnameException('Jumlah semua penyebab (' . $this->angka($total) . ') harus sama dengan selisih (' . $this->angka(abs($selisih)) . ' ' . $row->satuan . ').');
        }
        DB::transaction(function () use ($row, $siap, $lama) {
            $dipakai = collect($siap)->pluck('foto')->filter()->all();
            foreach ($lama as $l) {
                if ($l->foto && ! in_array($l->foto, $dipakai, true)) {
                    Storage::disk('local')->delete($l->foto);
                }
            }
            $row->rincian()->delete();
            foreach ($siap as $s) {
                $row->rincian()->create($s);
            }
        });
    }

    private function simpanFoto(StokOpname $o, string $isi): string
    {
        if (strlen($isi) > self::MAKS_FOTO) {
            throw new StokOpnameException('Foto maksimal 4 MB.');
        }
        $ext = ReturOnlineService::MIME_FOTO[(new \finfo(FILEINFO_MIME_TYPE))->buffer($isi)] ?? null;
        if (! $ext) {
            throw new StokOpnameException('Foto harus gambar JPG/PNG/WebP.');
        }
        $path = self::FOLDER_FOTO . '/' . now()->format('Ym') . '/' . $o->nomor . '-' . Str::random(10) . '.' . $ext;
        Storage::disk('local')->put($path, $isi);

        return $path;
    }

    private function hapusRincian(StokOpnameItem $row): void
    {
        foreach ($row->rincian()->get() as $r) {
            if ($r->foto) {
                Storage::disk('local')->delete($r->foto);
            }
        }
        $row->rincian()->delete();
    }

    // ───────────────────────────── periksa & ajukan ─────────────────────────────

    /** Masalah yang menghalangi pengajuan (kosong = siap diajukan). Juga dipakai layar untuk tanda per barang. */
    public function masalah(StokOpname $o): array
    {
        $rows = StokOpnameItem::with(['barang', 'rincian'])->where('stok_opname_id', $o->id)->get();
        $batas = (float) (PengaturanUmum::getSetting()->opname_batas_foto_hilang ?? 200000);
        $belum = $rows->whereNull('qty_fisik');
        $ulang = $rows->where('minta_hitung_ulang', true);
        $out = [];
        if ($belum->isNotEmpty()) {
            $out[] = $belum->count() . ' barang belum dihitung: ' . Str::limit($belum->map(fn ($r) => $r->barang?->nama_item)->implode(', '), 200) . '. Barang yang tidak ada di rak diisi 0.';
        }
        if ($ulang->isNotEmpty()) {
            $out[] = $ulang->count() . ' barang diminta hitung ulang: ' . Str::limit($ulang->map(fn ($r) => $r->barang?->nama_item)->implode(', '), 200) . '.';
        }
        foreach ($rows->whereNotNull('qty_fisik') as $r) {
            $s = $r->selisih;
            if ($s === null || $s >= 0) {
                continue;
            }
            $nama = $r->barang?->nama_item;
            $jml = round((float) $r->rincian->sum('qty'), 3);
            if (abs($jml - abs($s)) > 0.0005) {
                $out[] = "{$nama}: isi penyebab kurang " . $this->angka(abs($s)) . " {$r->satuan}.";
                continue;
            }
            $harga = $this->stok->hargaPokokRata((int) $r->item_id, (int) $o->cabang_id);
            foreach ($r->rincian as $x) {
                if ($x->foto) {
                    continue;
                }
                if ($x->penyebab === 'rusak') {
                    $out[] = "{$nama}: barang rusak wajib difoto.";
                } elseif ($x->penyebab === 'hilang' && $x->qty * $harga >= $batas) {
                    $out[] = "{$nama}: barang hilang senilai " . $this->rupiah($x->qty * $harga) . ' wajib difoto (batas ' . $this->rupiah($batas) . ').';
                }
            }
        }

        return $out;
    }

    public function ajukan(User $u, StokOpname $o): StokOpname
    {
        if (! $this->boleh($u, $o, 'periksa')) {
            throw new StokOpnameException('Anda tidak bisa mengajukan opname ini.');
        }

        return DB::transaction(function () use ($u, $o) {
            $o = StokOpname::whereKey($o->id)->lockForUpdate()->firstOrFail();
            if ($o->status !== 'hitung') {
                throw new StokOpnameException('Opname ini sudah ' . strtolower($o->status_label) . '.');
            }
            $masalah = $this->masalah($o);
            if ($masalah) {
                throw new StokOpnameException('Belum bisa diajukan: ' . implode(' ', array_slice($masalah, 0, 5)) . (count($masalah) > 5 ? ' (+' . (count($masalah) - 5) . ' lagi)' : ''));
            }
            foreach (StokOpnameItem::where('stok_opname_id', $o->id)->get() as $r) {
                $r->harga_pokok = $this->stok->hargaPokokRata((int) $r->item_id, (int) $o->cabang_id); // perkiraan; final saat disetujui
                $r->save();
                if ($r->selisih > 0) { // lebih: satu rincian otomatis
                    $r->rincian()->where('penyebab', '!=', 'lebih')->delete();
                    $r->rincian()->updateOrCreate(['penyebab' => 'lebih'], ['qty' => $r->selisih]);
                }
            }
            $o->update(['status' => 'diajukan', 'diajukan_oleh' => $u->id, 'diajukan_at' => now(), 'alasan_tolak' => null]);
            $this->log($o, 'ajukan', $u);
            DB::afterCommit(fn () => $this->kabari($o, 'diajukan', $u));

            return $o;
        });
    }

    public function tolak(User $u, StokOpname $o, string $alasan): StokOpname
    {
        $alasan = trim($alasan);
        if (! $this->boleh($u, $o, 'setujui')) {
            throw new StokOpnameException($this->sebabTidakBolehSetujui($u, $o));
        }
        if (mb_strlen($alasan) < 5) {
            throw new StokOpnameException('Tulis alasan penolakan (minimal 5 huruf) supaya penghitung tahu apa yang harus diperbaiki.');
        }

        return DB::transaction(function () use ($u, $o, $alasan) {
            $o = StokOpname::whereKey($o->id)->lockForUpdate()->firstOrFail();
            if ($o->status !== 'diajukan') {
                throw new StokOpnameException('Opname ini tidak sedang menunggu persetujuan.');
            }
            $o->update(['status' => 'hitung', 'alasan_tolak' => Str::limit($alasan, 500, '')]);
            $this->log($o, 'tolak', $u, $alasan);
            DB::afterCommit(fn () => $this->kabari($o, 'ditolak', $u));

            return $o;
        });
    }

    public function sebabTidakBolehSetujui(User $u, StokOpname $o): string
    {
        $o->loadMissing('cabang');
        if ($o->status !== 'diajukan') {
            return 'Opname ini tidak sedang menunggu persetujuan.';
        }
        if ($o->milik_mitra && $u->canAccessAllBranches()) {
            return 'Opname toko mitra disetujui pemilik toko itu sendiri. Owner BABA hanya bisa melihat.';
        }
        if (! $u->can('stok_opname.setujui') || ! $o->cabang || ! $this->bolehUbahLokasi($u, $o->cabang)) {
            return 'Anda tidak punya izin menyetujui opname di lokasi ini.';
        }

        return 'Anda ikut menghitung opname ini, jadi tidak bisa menyetujuinya sendiri. Minta Owner atau penyetuju lain.';
    }

    // ───────────────────────────── setujui ─────────────────────────────

    public function setujui(User $u, StokOpname $o): StokOpname
    {
        if (! $this->boleh($u, $o, 'setujui')) {
            throw new StokOpnameException($this->sebabTidakBolehSetujui($u, $o));
        }

        return DB::transaction(function () use ($u, $o) {
            $o = StokOpname::with('cabang')->whereKey($o->id)->lockForUpdate()->firstOrFail();
            if ($o->status !== 'diajukan') {
                throw new StokOpnameException('Opname ini tidak sedang menunggu persetujuan.');
            }
            $lokasi = (int) $o->cabang_id;
            $perPenyebab = [];
            $ring = ['dihitung' => 0, 'cocok' => 0, 'kurang' => 0, 'lebih' => 0, 'nilai_kurang' => 0.0, 'nilai_lebih' => 0.0];
            $rows = StokOpnameItem::with(['barang', 'rincian'])->where('stok_opname_id', $o->id)->orderBy('id')->get();
            foreach ($rows as $r) {
                $ring['dihitung']++;
                $s = (float) $r->selisih;
                $nama = $r->barang?->nama_item ?? ('#' . $r->item_id);
                if (abs($s) < 0.0005) {
                    $ring['cocok']++;
                    $r->update(['harga_pokok' => $this->stok->hargaPokokRata((int) $r->item_id, $lokasi), 'nilai_selisih' => 0]);
                    continue;
                }
                if ($s < 0) {
                    $ring['kurang']++;
                    $nilaiBarang = 0.0;
                    foreach ($r->rincian as $x) {
                        $nilai = round($this->stok->selisihOpname((int) $r->item_id, $lokasi, -$x->qty, 0, "{$o->nomor} · {$x->penyebab_label}" . ($x->catatan ? " — {$x->catatan}" : ''), $o->id), 2);
                        $x->update(['nilai' => $nilai]);
                        $nilaiBarang += $nilai;
                        $perPenyebab[$x->penyebab]['nilai'] = ($perPenyebab[$x->penyebab]['nilai'] ?? 0) + $nilai;
                        $perPenyebab[$x->penyebab]['barang'][$nama] = true;
                    }
                    $ring['nilai_kurang'] += $nilaiBarang;
                    $r->update(['harga_pokok' => round($nilaiBarang / abs($s), 4), 'nilai_selisih' => -round($nilaiBarang, 2)]);
                } else {
                    $ring['lebih']++;
                    $harga = $this->stok->hargaPokokRata((int) $r->item_id, $lokasi);
                    $nilai = round($this->stok->selisihOpname((int) $r->item_id, $lokasi, $s, $harga, "{$o->nomor} · Lebih (fisik > sistem)", $o->id), 2);
                    $r->rincian()->updateOrCreate(['penyebab' => 'lebih'], ['qty' => $s, 'nilai' => $nilai]);
                    $ring['nilai_lebih'] += $nilai;
                    $perPenyebab['lebih']['nilai'] = ($perPenyebab['lebih']['nilai'] ?? 0) + $nilai;
                    $perPenyebab['lebih']['barang'][$nama] = true;
                    $r->update(['harga_pokok' => $harga, 'nilai_selisih' => $nilai]);
                }
            }
            foreach ($perPenyebab as $kode => $p) {
                $p['nilai'] = round($p['nilai'], 2);
                $perPenyebab[$kode] = ['nilai' => $p['nilai'], 'barang' => count($p['barang'])];
                if ($p['nilai'] > 0) {
                    $this->catatKeuangan($o, $kode, $p['nilai'], count($p['barang']), $u);
                }
            }
            $ring['nilai_kurang'] = round($ring['nilai_kurang'], 2);
            $ring['nilai_lebih'] = round($ring['nilai_lebih'], 2);
            $ring['nilai_bersih'] = round($ring['nilai_lebih'] - $ring['nilai_kurang'], 2);
            $ring['akurasi'] = $ring['dihitung'] ? round($ring['cocok'] / $ring['dihitung'] * 100, 1) : 0;
            $ring['per_penyebab'] = $perPenyebab;
            $o->update(['status' => 'selesai', 'disetujui_oleh' => $u->id, 'disetujui_at' => now(), 'ringkasan' => $ring]);
            $this->log($o, 'setujui', $u, 'Nilai kurang ' . $this->rupiah($ring['nilai_kurang']) . ', lebih ' . $this->rupiah($ring['nilai_lebih']) . ", akurasi {$ring['akurasi']}%");
            DB::afterCommit(fn () => $this->kabari($o, 'disetujui', $u));

            return $o;
        });
    }

    /** Satu transaksi keuangan per penyebab per opname (tanpa kas). Toko mitra ikut tercatat di cabangnya — di luar keuangan BABA. */
    private function catatKeuangan(StokOpname $o, string $penyebab, float $nilai, int $jmlBarang, User $u): void
    {
        $p = self::PENYEBAB[$penyebab];
        $kat = self::kategori($penyebab);
        TransaksiKeuangan::create([
            'cabang_id' => $o->cabang_id, 'kas_id' => null,
            'nomor_transaksi' => $o->nomor . '-' . strtoupper(substr(str_replace('_', '', $penyebab), 0, 6)),
            'tanggal_transaksi' => today()->toDateString(),
            'tipe' => $p['tipe'] === 'pemasukan' ? TipeTransaksiKeuangan::Pemasukan : TipeTransaksiKeuangan::Pengeluaran,
            'kategori' => KategoriEnum::Lainnya, 'kategori_id' => $kat->id,
            'keterangan' => "Stok opname {$o->nomor}: {$p['label']} ({$jmlBarang} barang)",
            'jumlah' => $nilai, 'referensi_type' => 'stok_opname', 'referensi_id' => $o->id, 'created_by' => $u->id,
        ]);
    }

    /** Kategori transaksi per penyebab (dibuat bila belum ada — seeder juga membuatnya). */
    public static function kategori(string $penyebab): KategoriTransaksi
    {
        $p = self::PENYEBAB[$penyebab];

        return KategoriTransaksi::firstOrCreate(['kode' => $p['kategori']], [
            'nama' => $p['nama'], 'tipe' => $p['tipe'], 'kode_akun_coa' => $p['coa'], 'is_system' => true, 'is_active' => true, 'urutan' => 17,
        ]);
    }

    // ───────────────────────────── batal ─────────────────────────────

    public function batal(User $u, StokOpname $o, string $alasan): StokOpname
    {
        $alasan = trim($alasan);
        if (! $this->boleh($u, $o, 'batal')) {
            throw new StokOpnameException($o->berjalan ? 'Anda tidak punya izin membatalkan opname ini.' : 'Opname yang sudah ' . strtolower($o->status_label) . ' tidak bisa dibatalkan.');
        }
        if (mb_strlen($alasan) < 5) {
            throw new StokOpnameException('Tulis alasan pembatalan (minimal 5 huruf).');
        }

        return DB::transaction(function () use ($u, $o, $alasan) {
            $o = StokOpname::whereKey($o->id)->lockForUpdate()->firstOrFail();
            if (! $o->berjalan) {
                throw new StokOpnameException('Opname ini sudah ' . strtolower($o->status_label) . '.');
            }
            $o->update(['status' => 'batal', 'dibatalkan_oleh' => $u->id, 'alasan_batal' => Str::limit($alasan, 500, '')]);
            $this->log($o, 'batal', $u, $alasan);

            return $o;
        });
    }

    // ───────────────────────────── berita acara (Tahap 7b) ─────────────────────────────

    /**
     * Data Berita Acara Stok Opname (PDF & Excel). Opname selesai = angka final tersimpan; belum selesai = perkiraan
     * (harga pokok rata-rata sekarang) dan diberi tanda SEMENTARA.
     */
    public function beritaAcara(StokOpname $o): array
    {
        $o->loadMissing(['cabang', 'pembuat', 'pengaju', 'penyetuju', 'pembatal']);
        $final = $o->status === 'selesai';
        $rows = StokOpnameItem::with(['barang.category', 'rincian', 'penghitung'])->where('stok_opname_id', $o->id)
            ->join('items', 'items.id', '=', 'stok_opname_item.item_id')->orderBy('items.nama_item')->select('stok_opname_item.*')->get();
        $baris = [];
        $ring = ['dihitung' => 0, 'belum' => 0, 'cocok' => 0, 'kurang' => 0, 'lebih' => 0, 'nilai_kurang' => 0.0, 'nilai_lebih' => 0.0];
        $perPenyebab = [];
        $foto = [];
        foreach ($rows as $r) {
            $s = $r->selisih;
            $harga = $final ? (float) $r->harga_pokok : $this->stok->hargaPokokRata((int) $r->item_id, (int) $o->cabang_id);
            $nilai = $final ? (float) $r->nilai_selisih : ($s === null ? 0.0 : round($s * $harga, 2));
            if ($s === null) {
                $ring['belum']++;
            } else {
                $ring['dihitung']++;
                if (abs($s) < 0.0005) {
                    $ring['cocok']++;
                } elseif ($s < 0) {
                    $ring['kurang']++;
                    $ring['nilai_kurang'] += abs($nilai);
                } else {
                    $ring['lebih']++;
                    $ring['nilai_lebih'] += $nilai;
                }
            }
            $penyebab = [];
            foreach ($r->rincian as $x) {
                $nx = $final && $x->nilai !== null ? (float) $x->nilai : round($x->qty * $harga, 2);
                $penyebab[] = $x->penyebab_label . ' ' . $this->angka($x->qty) . ($x->catatan ? " ({$x->catatan})" : '');
                $perPenyebab[$x->penyebab]['nilai'] = ($perPenyebab[$x->penyebab]['nilai'] ?? 0) + $nx;
                $perPenyebab[$x->penyebab]['qty'][$r->satuan] = ($perPenyebab[$x->penyebab]['qty'][$r->satuan] ?? 0) + $x->qty;
                if ($x->foto) {
                    $foto[] = ['barang' => $r->barang?->nama_item, 'penyebab' => $x->penyebab_label, 'qty' => $this->angka($x->qty) . ' ' . $r->satuan, 'path' => $x->foto];
                }
            }
            if ($s !== null && $s > 0 && ! $r->rincian->contains('penyebab', 'lebih')) {
                $penyebab[] = self::PENYEBAB['lebih']['label'];
                $perPenyebab['lebih']['nilai'] = ($perPenyebab['lebih']['nilai'] ?? 0) + $nilai;
            }
            $baris[] = [
                'kode' => $r->barang?->kode_item, 'nama' => $r->barang?->nama_item, 'kategori' => $r->barang?->category?->nama_kategori, 'satuan' => $r->satuan,
                'sistem' => $r->qty_sistem, 'fisik' => $r->qty_fisik, 'selisih' => $s, 'harga' => $harga, 'nilai' => $s === null ? null : $nilai,
                'penyebab' => implode('; ', $penyebab), 'hitung_ke' => $r->hitung_ke, 'penghitung' => $r->penghitung?->name,
            ];
        }
        $ring['nilai_kurang'] = round($ring['nilai_kurang'], 2);
        $ring['nilai_lebih'] = round($ring['nilai_lebih'], 2);
        $ring['nilai_bersih'] = round($ring['nilai_lebih'] - $ring['nilai_kurang'], 2);
        $ring['akurasi'] = $ring['dihitung'] ? round($ring['cocok'] / $ring['dihitung'] * 100, 1) : 0;
        ksort($perPenyebab);

        return [
            'o' => $o, 'final' => $final, 'baris' => $baris, 'ringkasan' => $ring, 'per_penyebab' => $perPenyebab, 'foto' => $foto,
            'penghitung' => $rows->pluck('penghitung.name')->filter()->unique()->values()->all(),
            'selesai_at' => $o->disetujui_at,
        ];
    }

    // ───────────────────────────── pendukung ─────────────────────────────

    /** Jumlah opname yang menunggu persetujuan user ini (angka merah menu). */
    public function jumlahMenunggu(User $u): int
    {
        if (! $u->can('stok_opname.setujui')) {
            return 0;
        }

        return StokOpname::with('cabang')->where('status', 'diajukan')->get()->filter(fn ($o) => $this->boleh($u, $o, 'setujui'))->count();
    }

    private function kabari(StokOpname $o, string $kejadian, User $pelaku): void
    {
        try {
            $o->loadMissing('cabang');
            if ($kejadian === 'diajukan') {
                $ke = User::where('is_active', true)->get()->filter(fn (User $x) => $x->id !== $pelaku->id && $this->boleh($x, $o, 'setujui'));
            } else {
                $ids = array_unique(array_filter(array_merge([(int) $o->diajukan_oleh, (int) $o->dibuat_oleh], $this->penghitungIds($o))));
                $ke = User::where('is_active', true)->whereIn('id', $ids)->where('id', '!=', $pelaku->id)->get();
            }
            NotificationService::send($ke, new StokOpnameNotification($o, $kejadian, $pelaku->name));
        } catch (\Throwable $e) {
            Log::warning(__METHOD__ . " {$o->nomor}: " . $e->getMessage());
        }
    }

    private function log(StokOpname $o, string $aksi, User $u, ?string $catatan = null): void
    {
        StokOpnameLog::create(['stok_opname_id' => $o->id, 'aksi' => $aksi, 'user_id' => $u->id, 'catatan' => $catatan ? Str::limit($catatan, 500, '') : null, 'created_at' => now()]);
    }

    public function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', '.'), '0'), ',');
    }

    public function rupiah(float $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }
}
