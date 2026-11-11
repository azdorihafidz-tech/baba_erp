<?php

namespace App\Services;

use App\Exceptions\PesananException;
use App\Models\MemberAkun;
use App\Models\PenjualanMember;
use App\Models\PesananOnline;
use App\Models\PesananOnlineItem;
use App\Models\ProdukOnline;
use App\Models\StokMember;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 3 — kasir member (Toko Saya › Kasir): jual parfum dari stok member ke pelanggan, struk,
 * batal (hari yang sama, stok kembali), dan 3 laporan (transaksi, produk, kategori — rancangan §8).
 * Penjualan kasir TIDAK menambah RO.
 */
class KasirMemberService
{
    public function __construct(private StokMemberService $stok)
    {
    }

    /** Barang yang bisa dijual (stok > 0) + harga jual (pilihan member → harga tetap produk online → modal). */
    public function produk(MemberAkun $m): array
    {
        $stok = StokMember::with('item')->where('member_id', $m->member_id)->where('qty', '>', 0)->get();
        $online = ProdukOnline::with('harga')->whereIn('item_id', $stok->pluck('item_id'))->get()->keyBy('item_id');

        return $stok->map(function (StokMember $s) use ($online) {
            $p = $online->get($s->item_id);
            $dipesan = app(PesananOnlineService::class)->dipesanMember($s->item_id, $s->member_id);

            return ['item_id' => $s->item_id, 'nama' => $p?->nama_tampil ?? $s->item?->nama_item, 'kategori' => $p?->kategori,
                'foto' => $p?->fotoUrl(), 'stok' => max(0, $s->qty - $dipesan), 'modal' => (float) $s->nilai_rata,
                'harga' => (float) ($s->harga_jual ?? $p?->hargaJenis('tetap') ?? $s->nilai_rata)];
        })->filter(fn ($x) => $x['stok'] > 0)->sortBy('nama')->values()->all();
    }

    /**
     * @param array{item: array<int, array{item_id:int, qty:int, harga:mixed}>, bayar:string, diterima?:mixed,
     *              pelanggan_nama?:string, pelanggan_hp?:string, catatan?:string, simpan_harga?:bool} $d
     */
    public function jual(MemberAkun $m, array $d): PenjualanMember
    {
        $bayar = $d['bayar'] ?? '';
        if (! array_key_exists($bayar, PenjualanMember::BAYAR)) {
            throw new PesananException('Pilih cara bayar: tunai, transfer, atau QRIS.');
        }
        $baris = [];
        foreach ((array) ($d['item'] ?? []) as $i) {
            $id = (int) ($i['item_id'] ?? 0);
            $qty = $i['qty'] ?? 0;
            $harga = $i['harga'] ?? null;
            if ($id <= 0 || ! is_numeric($qty) || (int) $qty != $qty || (int) $qty < 1 || (int) $qty > 999) {
                throw new PesananException('Jumlah barang harus angka bulat 1–999.');
            }
            if (! is_numeric($harga) || (float) $harga < 0 || (float) $harga > 100000000) {
                throw new PesananException('Harga jual tidak valid.');
            }
            $baris[] = ['item_id' => $id, 'qty' => (int) $qty, 'harga' => round((float) $harga, 2)];
        }
        if (! $baris) {
            throw new PesananException('Keranjang kasir masih kosong.');
        }
        foreach (['pelanggan_nama' => 100, 'pelanggan_hp' => 30, 'catatan' => 255] as $k => $maks) {
            if (mb_strlen(trim((string) ($d[$k] ?? ''))) > $maks) {
                throw new PesananException('Data pelanggan / catatan terlalu panjang.');
            }
        }

        return DB::transaction(function () use ($m, $d, $baris, $bayar) {
            $online = ProdukOnline::whereIn('item_id', collect($baris)->pluck('item_id'))->get()->keyBy('item_id');
            $total = 0.0;
            $modal = 0.0;
            $isi = [];
            foreach ($baris as $b) {
                $s = StokMember::with('item')->where('member_id', $m->member_id)->where('item_id', $b['item_id'])->lockForUpdate()->first();
                $ada = $s ? $s->qty - app(PesananOnlineService::class)->dipesanMember($b['item_id'], $m->member_id) : 0;
                $nama = $online->get($b['item_id'])?->nama_tampil ?? $s?->item?->nama_item ?? 'Barang';
                if (! $s) {
                    throw new PesananException('Barang ini tidak ada di stok Anda.');
                }
                if ($ada < $b['qty']) {
                    throw new PesananException("Stok {$nama} Anda tinggal " . max(0, $ada) . '.');
                }
                $isi[] = $b + ['nama' => $nama, 'kategori' => $online->get($b['item_id'])?->kategori, 'subtotal' => $b['qty'] * $b['harga']];
                $total += $b['qty'] * $b['harga'];
                if (! empty($d['simpan_harga'])) {
                    $s->update(['harga_jual' => $b['harga']]);
                }
            }
            $diterima = $d['diterima'] ?? null;
            if ($bayar === 'tunai' && $diterima !== null && $diterima !== '' && (! is_numeric($diterima) || (float) $diterima < $total)) {
                throw new PesananException('Uang diterima kurang dari total.');
            }
            $pj = PenjualanMember::create([
                'nomor' => $this->nomorBaru(), 'member_id' => $m->member_id, 'total' => $total, 'bayar' => $bayar,
                'diterima' => $bayar === 'tunai' && is_numeric($diterima) ? (float) $diterima : null,
                'pelanggan_nama' => trim((string) ($d['pelanggan_nama'] ?? '')) ?: null, 'pelanggan_hp' => trim((string) ($d['pelanggan_hp'] ?? '')) ?: null,
                'catatan' => trim((string) ($d['catatan'] ?? '')) ?: null, 'status' => 'selesai',
            ]);
            foreach ($isi as $r) {
                $rata = $this->stok->keluar($m->member_id, $r['item_id'], $r['qty'], 'kasir', 'penjualan_member', $pj->id, 'Kasir ' . $pj->nomor);
                $pj->item()->create(['item_id' => $r['item_id'], 'nama' => $r['nama'], 'kategori' => $r['kategori'], 'qty' => $r['qty'],
                    'harga' => $r['harga'], 'subtotal' => $r['subtotal'], 'modal' => $rata]);
                $modal += $rata * $r['qty'];
            }
            $pj->update(['modal' => $modal]);

            return $pj->load('item');
        });
    }

    /** Batal penjualan kasir (hanya di hari yang sama): stok kembali. */
    public function batal(PenjualanMember $pj, MemberAkun $m, ?string $alasan): PenjualanMember
    {
        if ($pj->member_id !== $m->member_id) {
            throw new PesananException('Transaksi tidak ditemukan.');
        }

        return DB::transaction(function () use ($pj, $m, $alasan) {
            $p = PenjualanMember::lockForUpdate()->findOrFail($pj->id);
            if ($p->status === 'batal') {
                throw new PesananException('Transaksi ini sudah dibatalkan.');
            }
            if (! $p->created_at->isToday()) {
                throw new PesananException('Hanya transaksi hari ini yang bisa dibatalkan.');
            }
            foreach ($p->item as $i) {
                $this->stok->masuk($m->member_id, $i->item_id, $i->qty, (float) $i->modal, 'kasir_batal', 'penjualan_member', $p->id, 'Batal kasir ' . $p->nomor);
            }
            $p->update(['status' => 'batal', 'batal_at' => now(), 'batal_alasan' => mb_substr(trim((string) $alasan), 0, 255) ?: 'Dibatalkan']);

            return $p;
        });
    }

    public function struk(PenjualanMember $p): array
    {
        $p->loadMissing(['item', 'member']);

        return ['nomor' => $p->nomor, 'tanggal' => $p->created_at?->toIso8601String(), 'status' => $p->status, 'total' => (float) $p->total,
            'bayar' => $p->bayar, 'bayar_label' => PenjualanMember::BAYAR[$p->bayar] ?? $p->bayar, 'diterima' => $p->diterima === null ? null : (float) $p->diterima,
            'kembali' => $p->diterima === null ? null : max(0, (float) $p->diterima - (float) $p->total),
            'pelanggan_nama' => $p->pelanggan_nama, 'pelanggan_hp' => $p->pelanggan_hp, 'catatan' => $p->catatan, 'batal_alasan' => $p->batal_alasan,
            'penjual' => $p->member?->nama, 'penjual_no_id' => $p->member?->no_id,
            'item' => $p->item->map(fn ($i) => ['nama' => $i->nama, 'qty' => $i->qty, 'harga' => (float) $i->harga, 'subtotal' => (float) $i->subtotal])->values(),
            'boleh_batal' => $p->status === 'selesai' && $p->created_at->isToday()];
    }

    // ===================== laporan (rancangan §8: transaksi, produk, kategori) =====================

    private function rentang(?string $dari, ?string $sampai): array
    {
        try {
            $a = $dari ? Carbon::parse($dari)->startOfDay() : now()->startOfMonth();
            $b = $sampai ? Carbon::parse($sampai)->endOfDay() : now()->endOfDay();
        } catch (\Throwable) {
            throw new PesananException('Tanggal laporan tidak valid.');
        }
        if ($a->gt($b) || $a->diffInDays($b) > 370) {
            throw new PesananException('Rentang tanggal laporan tidak valid (maks 1 tahun).');
        }

        return [$a, $b];
    }

    public function laporan(MemberAkun $m, string $jenis, ?string $dari, ?string $sampai): array
    {
        [$a, $b] = $this->rentang($dari, $sampai);
        $jual = PenjualanMember::with('item')->where('member_id', $m->member_id)->where('status', 'selesai')->whereBetween('created_at', [$a, $b])->get();
        $beli = PesananOnline::with('item')->where('member_id', $m->member_id)->where('status', 'diterima')->whereBetween('diterima_at', [$a, $b])->get();
        $ringkas = ['penjualan' => (float) $jual->sum('total'), 'modal' => (float) $jual->sum('modal'), 'laba' => (float) ($jual->sum('total') - $jual->sum('modal')),
            'transaksi' => $jual->count(), 'pembelian' => (float) $beli->sum(fn ($p) => (float) $p->total - (float) $p->nilai_retur), 'botol_terjual' => (int) $jual->sum(fn ($p) => $p->item->sum('qty'))];
        $dasar = ['dari' => $a->toDateString(), 'sampai' => $b->toDateString(), 'ringkas' => $ringkas];

        if ($jenis === 'produk') {
            $stok = StokMember::with('item')->where('member_id', $m->member_id)->get()->keyBy('item_id');
            $baris = [];
            foreach ($jual as $p) {
                foreach ($p->item as $i) {
                    $r = &$baris[$i->item_id];
                    $r['nama'] ??= $i->nama;
                    $r['terjual'] = ($r['terjual'] ?? 0) + $i->qty;
                    $r['omzet'] = ($r['omzet'] ?? 0) + (float) $i->subtotal;
                    $r['modal'] = ($r['modal'] ?? 0) + (float) $i->modal * $i->qty;
                    unset($r);
                }
            }
            foreach ($beli as $p) {
                foreach ($p->item as $i) {
                    $r = &$baris[$i->item_id];
                    $r['nama'] ??= $i->nama;
                    $r['dibeli'] = ($r['dibeli'] ?? 0) + $i->qty - $i->qty_retur;
                    unset($r);
                }
            }
            foreach ($stok as $id => $s) {
                if ($s->qty > 0 && ! isset($baris[$id])) {
                    $baris[$id] = ['nama' => $s->item?->nama_item];
                }
            }
            $data = collect($baris)->map(fn ($r, $id) => ['item_id' => $id, 'nama' => $r['nama'], 'terjual' => $r['terjual'] ?? 0, 'dibeli' => $r['dibeli'] ?? 0,
                'omzet' => $r['omzet'] ?? 0, 'laba' => ($r['omzet'] ?? 0) - ($r['modal'] ?? 0), 'stok' => (int) ($stok[$id]->qty ?? 0)])
                ->sortByDesc('terjual')->values()->all();

            return $dasar + ['jenis' => 'produk', 'data' => $data];
        }
        if ($jenis === 'kategori') {
            $data = $jual->flatMap->item->groupBy(fn ($i) => $i->kategori ?: 'Tanpa kategori')
                ->map(fn ($g, $k) => ['kategori' => $k, 'terjual' => (int) $g->sum('qty'), 'omzet' => (float) $g->sum('subtotal'),
                    'laba' => (float) $g->sum(fn ($i) => (float) $i->subtotal - (float) $i->modal * $i->qty)])
                ->sortByDesc('omzet')->values()->all();

            return $dasar + ['jenis' => 'kategori', 'data' => $data];
        }
        // transaksi: penjualan kasir & pembelian (pesanan diterima) per tanggal
        $data = $jual->map(fn ($p) => ['tanggal' => $p->created_at->toIso8601String(), 'jenis' => 'jual', 'nomor' => $p->nomor, 'nilai' => (float) $p->total,
            'keterangan' => $p->item->map(fn ($i) => $i->nama . ' ×' . $i->qty)->implode(', ') . ' · ' . (PenjualanMember::BAYAR[$p->bayar] ?? $p->bayar)])
            ->concat($beli->map(fn ($p) => ['tanggal' => $p->diterima_at->toIso8601String(), 'jenis' => 'beli', 'nomor' => $p->nomor,
                'nilai' => (float) $p->total - (float) $p->nilai_retur, 'keterangan' => $p->item->map(fn ($i) => $i->nama . ' ×' . ($i->qty - $i->qty_retur))->implode(', ')]))
            ->sortByDesc('tanggal')->values()->all();

        return $dasar + ['jenis' => 'transaksi', 'data' => $data];
    }

    private function nomorBaru(): string
    {
        $awal = 'KSR-' . now()->format('Ym') . '-';
        $akhir = PenjualanMember::where('nomor', 'like', $awal . '%')->lockForUpdate()->orderByDesc('nomor')->value('nomor');

        return $awal . str_pad((string) ($akhir ? (int) substr($akhir, -4) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }
}
