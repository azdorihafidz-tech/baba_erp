<?php

namespace App\Services;

use App\Models\Item;
use App\Models\PemakaianPerlengkapan;
use Illuminate\Support\Facades\DB;

/**
 * Fase 5 — Modul Perlengkapan (Rule #66). Service BARU, murni memanggil
 * StokService::keluar() existing (TIDAK DIMODIFIKASI sama sekali) untuk
 * mengurangi stok — cegah risk regresi ke Adjustment Stok/StokService.
 */
class PemakaianPerlengkapanService
{
    public function __construct(private StokService $stokService) {}

    /**
     * Simpan 1 kejadian pemakaian perlengkapan + kurangi stok via
     * StokService::keluar() existing (throws Exception kalau stok kurang —
     * dibiarkan bubble up ke controller, tidak di-catch di sini supaya
     * transaction rollback benar kalau gagal).
     */
    public function simpan(array $data): PemakaianPerlengkapan
    {
        return DB::transaction(function () use ($data) {
            $item = Item::findOrFail($data['item_id']);

            $pemakaian = PemakaianPerlengkapan::create([
                'item_id'           => $data['item_id'],
                'cabang_id'         => $data['cabang_id'],
                'qty'               => $data['qty'],
                'tanggal_pemakaian' => $data['tanggal_pemakaian'],
                'keterangan'        => $data['keterangan'] ?? null,
                'created_by'        => auth()->id(),
            ]);

            $hpp = $this->stokService->keluar(
                (int) $data['item_id'],
                (int) $data['cabang_id'],
                (float) $data['qty'],
                'Pemakaian perlengkapan: ' . $item->nama_item . ($data['keterangan'] ? ' — ' . $data['keterangan'] : ''),
                'pemakaian_perlengkapan',
                $pemakaian->id,
            );

            $pemakaian->update(['nilai' => $hpp]);

            activity('pemakaian_perlengkapan')
                ->causedBy(auth()->user())
                ->performedOn($pemakaian)
                ->withProperties([
                    'item'      => $item->nama_item,
                    'cabang_id' => $data['cabang_id'],
                    'qty'       => (float) $data['qty'],
                    'nilai'     => $hpp,
                ])
                ->log(
                    (auth()->user()?->name ?? 'User') . ' mencatat pemakaian perlengkapan ' .
                    $item->nama_item . ' sebanyak ' . rtrim(rtrim(number_format((float) $data['qty'], 3, ',', '.'), '0'), ',') . ' ' . $item->satuan
                );

            return $pemakaian;
        });
    }
}
