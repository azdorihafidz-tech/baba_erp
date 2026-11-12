<?php

namespace App\Services;

use App\Models\MemberAkun;
use App\Models\PesananOnline;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ERP BABA Tahap 4 — laporan BABA dari belanja online (rancangan §8):
 * penjualan gudang per produk (HPP & laba) / cabang / jabatan / tim, RO per member & per tim, tagihan member.
 *
 * Penjualan = Pesanan Online GUDANG yang barangnya sudah keluar (tanggal dikirim / siap diambil) dalam periode,
 * bersih setelah retur yang disetujui (qty − qty_retur). Ongkir terpisah dari omzet produk.
 * RO = buku RO ERP (ro_catatan: + saat pesanan memenuhi Mode RO, − saat retur/batal) menurut tanggalnya — gudang & upline.
 * Tim = upline pada jabatan yang dipilih, dihitung aplikasi member dari struktur (parent_id) lewat AplikasiMemberClient::jalur.
 */
class LaporanOnlineService
{
    /** Level jabatan aplikasi member (JalurAtasanService): 0 TM … 6 Distributor. */
    public const LEVEL_TIM = [1 => 'MVO', 2 => 'Regional', 3 => 'Founder', 4 => 'GM', 5 => 'Supplier', 6 => 'Distributor'];

    public function __construct(private AplikasiMemberClient $member)
    {
    }

    /** Baris item penjualan gudang (bersih retur) dalam periode. */
    private function itemTerjual(Carbon $dari, Carbon $sampai, ?int $gudangId): Collection
    {
        return DB::table('pesanan_online_item as i')->join('pesanan_online as p', 'p.id', '=', 'i.pesanan_online_id')
            ->where('p.sumber', 'gudang')->where('p.stok_keluar', true)
            ->whereBetween('p.dikirim_at', [$dari->copy()->startOfDay(), $sampai->copy()->endOfDay()])
            ->when($gudangId, fn ($q) => $q->where('p.sumber_cabang_id', $gudangId))
            ->selectRaw('i.item_id, i.nama, p.member_id, p.id as pesanan_id, (i.qty - i.qty_retur) as qty, (i.qty - i.qty_retur) * i.harga as omzet,
                (i.qty - i.qty_retur) * COALESCE(i.hpp, 0) as hpp')
            ->get();
    }

    public function ringkas(Carbon $dari, Carbon $sampai, ?int $gudangId = null): array
    {
        $item = $this->itemTerjual($dari, $sampai, $gudangId);
        $ongkir = (float) PesananOnline::where('sumber', 'gudang')->where('stok_keluar', true)
            ->whereBetween('dikirim_at', [$dari->copy()->startOfDay(), $sampai->copy()->endOfDay()])
            ->when($gudangId, fn ($q) => $q->where('sumber_cabang_id', $gudangId))->sum('ongkir');
        $omzet = (float) $item->sum('omzet');
        $hpp = (float) $item->sum('hpp');

        return ['pesanan' => $item->pluck('pesanan_id')->unique()->count(), 'botol' => (int) $item->sum('qty'), 'omzet' => $omzet,
            'hpp' => $hpp, 'laba' => $omzet - $hpp, 'ongkir' => $ongkir, 'pembeli' => $item->pluck('member_id')->unique()->count()];
    }

    public function perProduk(Carbon $dari, Carbon $sampai, ?int $gudangId = null): Collection
    {
        return $this->itemTerjual($dari, $sampai, $gudangId)->groupBy('item_id')->map(fn ($g) => $this->angka($g, $g->first()->nama))
            ->sortByDesc('omzet')->values();
    }

    /** $oleh: cabang | jabatan (dari salinan akun member_akun). */
    public function perAkun(Carbon $dari, Carbon $sampai, string $oleh, ?int $gudangId = null): Collection
    {
        $item = $this->itemTerjual($dari, $sampai, $gudangId);
        $akun = MemberAkun::whereIn('member_id', $item->pluck('member_id')->unique())->get()->keyBy('member_id');

        return $item->groupBy(fn ($r) => ($oleh === 'cabang' ? $akun->get($r->member_id)?->cabang : $akun->get($r->member_id)?->jabatan) ?: 'Tidak diketahui')
            ->map(fn ($g, $k) => $this->angka($g, (string) $k))->sortByDesc('omzet')->values();
    }

    public function perTim(Carbon $dari, Carbon $sampai, int $level, ?int $gudangId = null): Collection
    {
        $item = $this->itemTerjual($dari, $sampai, $gudangId);
        $tim = $this->timUntuk($item->pluck('member_id')->unique()->all(), $level);

        return $item->groupBy(fn ($r) => $tim[$r->member_id]['kunci'])
            ->map(fn ($g, $k) => $this->angka($g, $tim[$g->first()->member_id]['label']) + ['tim_id' => $tim[$g->first()->member_id]['id']])
            ->sortByDesc('omzet')->values();
    }

    /** RO per member (buku RO ERP, menurut tanggal catatan). */
    public function roPerMember(Carbon $dari, Carbon $sampai): Collection
    {
        $ro = DB::table('ro_catatan')->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->selectRaw('member_id, SUM(CASE WHEN qty > 0 THEN qty ELSE 0 END) as tambah, SUM(CASE WHEN qty < 0 THEN -qty ELSE 0 END) as kurang, SUM(qty) as bersih,
                SUM(CASE WHEN status = \'terkirim\' THEN 0 ELSE 1 END) as tertunda')
            ->groupBy('member_id')->get();
        $akun = MemberAkun::whereIn('member_id', $ro->pluck('member_id'))->get()->keyBy('member_id');

        return $ro->map(fn ($r) => ['member_id' => (int) $r->member_id, 'nama' => $akun->get($r->member_id)?->nama ?? 'Akun #' . $r->member_id,
            'no_id' => $akun->get($r->member_id)?->no_id, 'no_member' => $akun->get($r->member_id)?->no_member, 'jabatan' => $akun->get($r->member_id)?->jabatan,
            'cabang' => $akun->get($r->member_id)?->cabang, 'tambah' => (int) $r->tambah, 'kurang' => (int) $r->kurang, 'bersih' => (int) $r->bersih,
            'tertunda' => (int) $r->tertunda])->sortByDesc('bersih')->values();
    }

    public function roPerTim(Carbon $dari, Carbon $sampai, int $level): Collection
    {
        $ro = $this->roPerMember($dari, $sampai);
        $tim = $this->timUntuk($ro->pluck('member_id')->all(), $level);

        return $ro->groupBy(fn ($r) => $tim[$r['member_id']]['kunci'])
            ->map(fn ($g) => ['nama' => $tim[$g->first()['member_id']]['label'], 'anggota' => $g->count(), 'tambah' => $g->sum('tambah'),
                'kurang' => $g->sum('kurang'), 'bersih' => $g->sum('bersih')])
            ->sortByDesc('bersih')->values();
    }

    /** Tagihan member (pesanan gudang bayar tagihan, belum lunas, tidak batal), jatuh tempo terdekat dulu. */
    public function tagihan(?int $gudangId = null): Collection
    {
        $hari = today();

        return PesananOnline::with('member')->where('sumber', 'gudang')->where('cara_bayar', 'tagihan')
            ->where('status_bayar', '!=', 'lunas')->where('status', '!=', 'batal')
            ->when($gudangId, fn ($q) => $q->where('sumber_cabang_id', $gudangId))
            ->orderByRaw('jatuh_tempo IS NULL, jatuh_tempo')->orderBy('id')->get()
            ->map(fn ($p) => ['nomor' => $p->nomor, 'id' => $p->id, 'nama' => $p->member?->nama ?? 'Akun #' . $p->member_id, 'no_id' => $p->member?->no_id,
                'jabatan' => $p->member?->jabatan, 'cabang' => $p->member?->cabang, 'tanggal' => $p->created_at, 'jatuh_tempo' => $p->jatuh_tempo,
                'sisa' => $p->sisa_bayar, 'barang_keluar' => (bool) $p->stok_keluar, 'status' => $p->status_label,
                'telat' => $p->jatuh_tempo && $p->jatuh_tempo->lt($hari) ? (int) $p->jatuh_tempo->diffInDays($hari) : 0,
                'sisa_hari' => $p->jatuh_tempo && $p->jatuh_tempo->gte($hari) ? (int) $hari->diffInDays($p->jatuh_tempo) : null]);
    }

    private function angka(Collection $g, string $nama): array
    {
        $omzet = (float) $g->sum('omzet');
        $hpp = (float) $g->sum('hpp');

        return ['nama' => $nama, 'botol' => (int) $g->sum('qty'), 'pesanan' => $g->pluck('pesanan_id')->unique()->count(),
            'pembeli' => $g->pluck('member_id')->unique()->count(), 'omzet' => $omzet, 'hpp' => $hpp, 'laba' => $omzet - $hpp,
            'margin' => $omzet > 0 ? round(($omzet - $hpp) / $omzet * 100, 1) : null];
    }

    /**
     * Upline pada level jabatan $level untuk setiap member (dirinya sendiri bila jabatannya level itu).
     *
     * @return array<int, array{kunci: string, label: string, id: ?int}>
     */
    public function timUntuk(array $memberIds, int $level): array
    {
        $jabatan = self::LEVEL_TIM[$level] ?? 'Tim';
        $jalur = $memberIds ? $this->member->jalur($memberIds) : [];
        $hasil = [];
        foreach ($memberIds as $id) {
            $j = $jalur[$id] ?? null;
            $ketua = ($j['diri']['level'] ?? null) === $level ? $j['diri'] : ($j['jalur'][$level] ?? null);
            $hasil[$id] = $ketua
                ? ['kunci' => 'u' . $ketua['id'], 'label' => trim(($ketua['no_id'] ? $ketua['no_id'] . ' — ' : '') . $ketua['nama']), 'id' => (int) $ketua['id']]
                : ['kunci' => 'tanpa', 'label' => "Tanpa {$jabatan} (di atas {$jabatan} / belum di bawah {$jabatan})", 'id' => null];
        }

        return $hasil;
    }
}
