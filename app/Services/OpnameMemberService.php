<?php

namespace App\Services;

use App\Exceptions\PesananException;
use App\Models\MemberAkun;
use App\Models\OpnameMember;
use App\Models\StokMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ERP BABA Tahap 7f — stok opname member (satu-satunya tempat aturannya; rancangan stok opname §11):
 * tanggung jawab member, langsung berlaku tanpa persetujuan, HANYA mengurangi stok (fisik lebih → ditolak, lapor admin),
 * penyebab wajib per barang (sama dengan opname gudang), foto opsional. Tidak mengubah RO; nilai = modal rata-rata member
 * (pembukuan member di Laporan Toko Saya, bukan keuangan BABA).
 */
class OpnameMemberService
{
    public const FOLDER_FOTO = 'opname-member';

    public function __construct(private StokMemberService $stok, private PesananOnlineService $pesanan)
    {
    }

    /**
     * @param array{item: array<int, array{item_id:mixed, fisik:mixed, penyebab?:string, catatan?:string}>, catatan?:string} $d  $foto = isi file (biner) atau null
     */
    public function simpan(MemberAkun $m, array $d, ?string $foto = null): OpnameMember
    {
        $baris = [];
        foreach (array_slice((array) ($d['item'] ?? []), 0, 200) as $i) {
            $itemId = (int) ($i['item_id'] ?? 0);
            if (! $itemId || ! isset($i['fisik']) || $i['fisik'] === '' || $i['fisik'] === null) {
                continue;
            }
            if (! is_numeric($i['fisik']) || (float) $i['fisik'] < 0 || floor((float) $i['fisik']) != (float) $i['fisik']) {
                throw new PesananException('Jumlah fisik harus bilangan bulat 0 atau lebih.');
            }
            $baris[$itemId] = ['fisik' => (int) $i['fisik'], 'penyebab' => (string) ($i['penyebab'] ?? ''),
                'catatan' => isset($i['catatan']) ? Str::limit(trim((string) $i['catatan']), 255, '') : null];
        }
        if (! $baris) {
            throw new PesananException('Isi jumlah fisik barang yang berkurang.');
        }
        $path = null;
        if ($foto !== null && $foto !== '') {
            if (strlen($foto) > StokOpnameService::MAKS_FOTO) {
                throw new PesananException('Foto maksimal 4 MB.');
            }
            $ext = ReturOnlineService::MIME_FOTO[(new \finfo(FILEINFO_MIME_TYPE))->buffer($foto)] ?? null;
            if (! $ext) {
                throw new PesananException('Foto harus gambar JPG/PNG/WebP.');
            }
            $path = self::FOLDER_FOTO . '/' . now()->format('Ym') . '/m' . $m->member_id . '-' . Str::random(12) . '.' . $ext;
        }

        try {
            return DB::transaction(function () use ($m, $d, $baris, $foto, $path) {
                $stok = StokMember::with('item')->where('member_id', $m->member_id)->whereIn('item_id', array_keys($baris))->lockForUpdate()->get()->keyBy('item_id');
                $kurang = [];
                foreach ($baris as $itemId => $b) {
                    $s = $stok->get($itemId);
                    $nama = $s?->item?->nama_item ?? "barang #{$itemId}";
                    $ada = (int) ($s?->qty ?? 0);
                    if (! $s) {
                        throw new PesananException("{$nama} tidak ada di stok Anda.");
                    }
                    if ($b['fisik'] > $ada) {
                        throw new PesananException("{$nama}: fisik {$b['fisik']} lebih banyak dari stok {$ada}. Stok lebih tidak bisa dicatat sendiri — hubungi admin gudang.");
                    }
                    if ($b['fisik'] === $ada) {
                        continue;
                    }
                    $dipesan = $this->pesanan->dipesanMember($itemId, $m->member_id);
                    if ($b['fisik'] < $dipesan) {
                        throw new PesananException("{$nama}: {$dipesan} sudah dipesan downline, lebih banyak dari fisik {$b['fisik']}. Selesaikan atau batalkan pesanan itu dulu di Pesanan Masuk.");
                    }
                    if (! in_array($b['penyebab'], StokOpnameService::PENYEBAB_KURANG, true)) {
                        throw new PesananException("{$nama}: pilih penyebab berkurangnya.");
                    }
                    $kurang[$itemId] = $b + ['ada' => $ada, 'nama' => $nama, 'satuan' => $s->item?->satuan];
                }
                if (! $kurang) {
                    throw new PesananException('Semua jumlah fisik sama dengan stok — tidak ada yang perlu dicatat.');
                }
                $prefix = 'OPM-' . now()->format('Ym') . '-';
                $akhir = OpnameMember::where('nomor', 'like', $prefix . '%')->lockForUpdate()->max(DB::raw('CAST(SUBSTRING(nomor, 12) AS UNSIGNED)'));
                $o = OpnameMember::create(['nomor' => $prefix . str_pad((string) (((int) $akhir) + 1), 4, '0', STR_PAD_LEFT), 'member_id' => $m->member_id,
                    'catatan' => isset($d['catatan']) ? Str::limit(trim((string) $d['catatan']), 255, '') : null, 'foto' => $path]);
                $total = 0.0;
                foreach ($kurang as $itemId => $k) {
                    $qty = $k['ada'] - $k['fisik'];
                    $label = StokOpnameService::PENYEBAB[$k['penyebab']]['label'];
                    $modal = $this->stok->keluar($m->member_id, $itemId, $qty, 'opname', 'opname_member', $o->id, "{$o->nomor} · {$label}" . ($k['catatan'] ? " — {$k['catatan']}" : ''));
                    $nilai = round($modal * $qty, 2);
                    $total += $nilai;
                    $o->item()->create(['item_id' => $itemId, 'nama' => $k['nama'], 'satuan' => $k['satuan'], 'stok_sebelum' => $k['ada'], 'fisik' => $k['fisik'],
                        'qty' => $qty, 'penyebab' => $k['penyebab'], 'nilai' => $nilai, 'catatan' => $k['catatan']]);
                }
                $o->update(['total_nilai' => round($total, 2)]);
                if ($path) {
                    Storage::disk('local')->put($path, $foto);
                }

                return $o->load('item');
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    /** Bentuk data untuk aplikasi member (daftar, rincian, cetak). */
    public function data(OpnameMember $o): array
    {
        $o->loadMissing(['item', 'member']);

        return ['nomor' => $o->nomor, 'tanggal' => $o->created_at?->toIso8601String(), 'total_nilai' => $o->total_nilai, 'catatan' => $o->catatan, 'ada_foto' => (bool) $o->foto,
            'penjual' => $o->member?->nama, 'penjual_no_id' => $o->member?->no_id,
            'item' => $o->item->map(fn ($i) => ['nama' => $i->nama, 'satuan' => $i->satuan, 'stok_sebelum' => $i->stok_sebelum, 'fisik' => $i->fisik, 'qty' => $i->qty,
                'penyebab' => $i->penyebab, 'penyebab_label' => $i->penyebab_label, 'nilai' => $i->nilai, 'catatan' => $i->catatan])->values()->all()];
    }
}
