<?php

namespace App\Services;

use App\Exceptions\PesananException;
use App\Models\Item;
use App\Models\StokMember;
use App\Models\StokMemberMutasi;

/**
 * ERP BABA Tahap 2c — stok parfum milik member. Satu-satunya jalan mengubah stok_member (selalu dicatat di _mutasi).
 * Nilai = harga beli rata-rata (dipakai sebagai modal saat dijual ke downline). Pemanggil wajib di dalam DB::transaction.
 */
class StokMemberService
{
    public function qty(int $memberId, int $itemId): int
    {
        return (int) StokMember::where('member_id', $memberId)->where('item_id', $itemId)->value('qty');
    }

    private function baris(int $memberId, int $itemId): StokMember
    {
        StokMember::firstOrCreate(['member_id' => $memberId, 'item_id' => $itemId], ['qty' => 0, 'nilai_rata' => 0]);

        return StokMember::where('member_id', $memberId)->where('item_id', $itemId)->lockForUpdate()->firstOrFail();
    }

    public function masuk(int $memberId, int $itemId, int $qty, float $harga, string $jenis, ?string $refType = null, ?int $refId = null, ?string $catatan = null): StokMember
    {
        if ($qty <= 0) {
            throw new PesananException('Jumlah stok masuk tidak valid.');
        }
        $s = $this->baris($memberId, $itemId);
        $lama = max(0, $s->qty);
        $s->nilai_rata = $lama + $qty > 0 ? round(($lama * (float) $s->nilai_rata + $qty * $harga) / ($lama + $qty), 2) : $harga;
        $s->qty += $qty;
        $s->save();
        $this->catat($s, $qty, $jenis, $harga, $refType, $refId, $catatan);

        return $s;
    }

    /** @return float nilai rata-rata per satuan saat keluar (modal) */
    public function keluar(int $memberId, int $itemId, int $qty, string $jenis, ?string $refType = null, ?int $refId = null, ?string $catatan = null): float
    {
        $s = $this->baris($memberId, $itemId);
        if ($qty <= 0 || $s->qty < $qty) {
            $nama = Item::find($itemId)?->nama_item ?? "barang #{$itemId}";
            throw new PesananException("Stok {$nama} tidak cukup (ada {$s->qty}, perlu {$qty}).");
        }
        $s->qty -= $qty;
        $s->save();
        $this->catat($s, -$qty, $jenis, (float) $s->nilai_rata, $refType, $refId, $catatan);

        return (float) $s->nilai_rata;
    }

    private function catat(StokMember $s, int $qty, string $jenis, ?float $harga, ?string $refType, ?int $refId, ?string $catatan): void
    {
        StokMemberMutasi::create(['member_id' => $s->member_id, 'item_id' => $s->item_id, 'qty' => $qty, 'sisa' => $s->qty, 'jenis' => $jenis,
            'harga' => $harga, 'referensi_type' => $refType, 'referensi_id' => $refId, 'catatan' => $catatan ? mb_substr($catatan, 0, 255) : null]);
    }
}
