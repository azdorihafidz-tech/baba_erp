<?php

namespace App\Services;

use App\Enums\StatusPurchaseOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan & breakdown status Purchase Order — dipakai widget Dashboard
 * utama (subset) dan Dashboard PO Full (semua method). Murni READ, tidak
 * menulis data apapun. Semua query pakai DB::table raw (bukan Eloquent)
 * supaya tidak perlu mikirin interaksi CabangScope + SoftDeletingScope —
 * filter cabang & exclude soft-deleted selalu eksplisit lewat parameter.
 *
 * "Sudah dibayar" dideteksi lewat transaksi_keuangans.referensi_type=
 * 'purchase_order' + referensi_id=po.id (reuse pola polymorphic yang
 * sudah dipakai PenjualanService untuk order — bukan kolom po_id baru).
 */
class PoDashboardService
{
    /**
     * 5 bucket ringkasan status. "Dalam Perjalanan" = spesifik status
     * dikirim_supplier. "Belum Diterima" = ROLLUP gabungan draft+disetujui+
     * dikirim_supplier (total PO yang belum sampai fisik, umur dihitung
     * dari created_at = total waktu PO ini outstanding).
     */
    public function getRingkasanStatus(?int $cabangId): array
    {
        $draft     = $this->fetchByStatus($cabangId, StatusPurchaseOrder::Draft->value);
        $disetujui = $this->fetchByStatus($cabangId, StatusPurchaseOrder::Disetujui->value);
        $dikirim   = $this->fetchByStatus($cabangId, StatusPurchaseOrder::DikirimSupplier->value);
        $belumBayar = $this->fetchBelumDibayar($cabangId);

        return [
            'menunggu_approval' => $this->bucketize($draft, 'created_at'),
            'perlu_dikirim'     => $this->bucketize($disetujui, 'approved_at'),
            'dalam_perjalanan'  => $this->bucketize($dikirim, 'tanggal_kirim'),
            'belum_diterima'    => $this->bucketize([...$draft, ...$disetujui, ...$dikirim], 'created_at'),
            'belum_dibayar'     => $this->bucketize($belumBayar, 'tanggal_terima'),
        ];
    }

    /**
     * Tabel PO aktif (semua status kecuali dibatalkan) dengan umur per baris.
     * Tanggal acuan umur MENGIKUTI status PO saat ini (draft=created_at,
     * disetujui=approved_at, dikirim_supplier=tanggal_kirim, diterima=tanggal_terima).
     */
    public function getBreakdownAktif(
        ?int $cabangId,
        ?string $status,
        ?int $supplierId,
        ?int $umurMin,
        ?Carbon $dari,
        ?Carbon $sampai,
        ?string $sort,
        ?string $dir
    ) {
        $query = DB::table('purchase_orders as po')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('cabangs as c', 'c.id', '=', 'po.cabang_id')
            ->whereNull('po.deleted_at')
            ->where('po.status', '!=', StatusPurchaseOrder::Dibatalkan->value);

        if ($cabangId) {
            $query->where('po.cabang_id', $cabangId);
        }
        if ($status) {
            $query->where('po.status', $status);
        }
        if ($supplierId) {
            $query->where('po.supplier_id', $supplierId);
        }
        if ($dari && $sampai) {
            $query->whereBetween('po.tanggal_po', [$dari->toDateString(), $sampai->toDateString()]);
        }

        $rows = $query->select(
            'po.id', 'po.nomor_po', 'po.status', 'po.total_harga', 'po.tanggal_po',
            'po.created_at', 'po.approved_at', 'po.tanggal_kirim', 'po.tanggal_terima',
            's.nama_supplier', 'c.nama_cabang'
        )->get();

        $rows = $rows->map(function ($row) {
            $statusEnum = StatusPurchaseOrder::from($row->status);
            $tglAcuan = match ($statusEnum) {
                StatusPurchaseOrder::Draft => $row->created_at,
                StatusPurchaseOrder::Disetujui => $row->approved_at,
                StatusPurchaseOrder::DikirimSupplier => $row->tanggal_kirim,
                StatusPurchaseOrder::Diterima => $row->tanggal_terima,
                default => null,
            };
            $row->umur_hari  = $tglAcuan ? (int) Carbon::parse($tglAcuan)->diffInDays(now()) : 0;
            $row->badge_umur = $this->badgeUmur($row->umur_hari);
            $row->status_label = $statusEnum->label();
            $row->status_badge_class = $statusEnum->badgeClass();
            $row->sudah_dibayar = $statusEnum === StatusPurchaseOrder::Diterima
                ? (bool) $this->cekSudahDibayar($row->id)
                : null;
            return $row;
        });

        if ($umurMin) {
            $rows = $rows->filter(fn ($r) => $r->umur_hari >= $umurMin)->values();
        }

        $sortableKolom = ['umur_hari', 'total_harga', 'tanggal_po'];
        $sortKey = in_array($sort, $sortableKolom) ? $sort : 'umur_hari';
        $dirKey  = $dir === 'asc' ? 'asc' : 'desc';

        $sorted = $dirKey === 'asc' ? $rows->sortBy($sortKey) : $rows->sortByDesc($sortKey);

        return $sorted->values();
    }

    /** Jumlah PO per status (untuk chart bar), exclude dibatalkan. */
    public function getChartPerStatus(?int $cabangId): array
    {
        $query = DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->where('status', '!=', StatusPurchaseOrder::Dibatalkan->value);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $counts = $query->select('status', DB::raw('COUNT(*) as jml'))
            ->groupBy('status')
            ->pluck('jml', 'status');

        $labels = [];
        $data   = [];
        foreach (StatusPurchaseOrder::cases() as $s) {
            if ($s === StatusPurchaseOrder::Dibatalkan) {
                continue;
            }
            $labels[] = $s->label();
            $data[]   = (int) ($counts[$s->value] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Trend jumlah PO dibuat per bulan, 6 bulan terakhir (rolling window).
     * Pola subMonths() dari startOfMonth() — hindari overflow tanggal 31
     * (lihat catatan sama di LaporanKonsumsiBahanService/LaporanLabaRugiService).
     */
    public function getTrendBulanan(?int $cabangId, ?Carbon $end = null): array
    {
        $end   = ($end ?? Carbon::now())->copy()->endOfMonth();
        $start = $end->copy()->startOfMonth()->subMonths(5);

        $query = DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->whereBetween('tanggal_po', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', StatusPurchaseOrder::Dibatalkan->value);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $rows = $query->select(
                DB::raw('YEAR(tanggal_po) as tahun'),
                DB::raw('MONTH(tanggal_po) as bulan'),
                DB::raw('COUNT(*) as jml')
            )
            ->groupBy('tahun', 'bulan')
            ->get()
            ->keyBy(fn ($r) => $r->tahun . '-' . str_pad((string) $r->bulan, 2, '0', STR_PAD_LEFT));

        $labels = [];
        $data   = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 6; $i++) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->translatedFormat('M Y');
            $data[]   = (int) ($rows[$key]->jml ?? 0);
            $cursor->addMonth();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /** PO diterima tapi belum ada transaksi_keuangan referensi — untuk dropdown "Pilih PO" & bucket "Belum Dibayar". */
    public function getPoPendingBayar(?int $cabangId)
    {
        $rows = $this->fetchBelumDibayar($cabangId, ['po.id', 'po.nomor_po', 'po.total_harga', 'po.tanggal_terima', 's.nama_supplier']);

        return collect($rows)->map(function ($row) {
            $row->umur_hari = $row->tanggal_terima ? (int) Carbon::parse($row->tanggal_terima)->diffInDays(now()) : 0;
            return $row;
        })->values();
    }

    /** Transaksi keuangan yang membayar PO ini, kalau ada (null = belum dibayar). */
    public function cekSudahDibayar(int $poId)
    {
        return DB::table('transaksi_keuangans')
            ->where('referensi_type', 'purchase_order')
            ->where('referensi_id', $poId)
            ->whereNull('deleted_at')
            ->select('id', 'nomor_transaksi', 'tanggal_transaksi', 'jumlah')
            ->first();
    }

    /**
     * Batch check status pembayaran untuk banyak PO sekaligus (Poin 1) — anti
     * N+1 dibanding panggil cekSudahDibayar() dalam loop per baris list.
     * DB::table raw + whereNull('deleted_at') eksplisit, konsisten pola
     * seluruh service ini (lihat class docblock), bukan Eloquent.
     *
     * @param  array<int>  $poIds
     * @return array<int, bool>  Map [po_id => sudah_dibayar]
     */
    public function getStatusPembayaranBatch(array $poIds): array
    {
        if (empty($poIds)) {
            return [];
        }

        $sudahIds = DB::table('transaksi_keuangans')
            ->where('referensi_type', 'purchase_order')
            ->whereIn('referensi_id', $poIds)
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('referensi_id')
            ->all();
        $sudahIds = array_flip($sudahIds);

        $result = [];
        foreach ($poIds as $id) {
            $result[$id] = isset($sudahIds[$id]);
        }

        return $result;
    }

    /** Badge visual umur — 0-3 hari biru, 4-7 kuning, >7 merah. Visual only, bukan alert/notifikasi. */
    public function badgeUmur(int $hari): string
    {
        if ($hari > 7) {
            return 'danger';
        }
        if ($hari >= 4) {
            return 'warning';
        }
        return 'primary';
    }

    private function fetchByStatus(?int $cabangId, string $status): array
    {
        $query = DB::table('purchase_orders')
            ->whereNull('deleted_at')
            ->where('status', $status);
        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        return $query->select('id', 'total_harga', 'created_at', 'approved_at', 'tanggal_kirim', 'tanggal_terima')
            ->get()
            ->all();
    }

    private function fetchBelumDibayar(?int $cabangId, array $select = ['po.id', 'po.total_harga', 'po.created_at', 'po.approved_at', 'po.tanggal_kirim', 'po.tanggal_terima'])
    {
        $query = DB::table('purchase_orders as po')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->whereNull('po.deleted_at')
            ->where('po.status', StatusPurchaseOrder::Diterima->value)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('transaksi_keuangans as tk')
                    ->whereColumn('tk.referensi_id', 'po.id')
                    ->where('tk.referensi_type', 'purchase_order')
                    ->whereNull('tk.deleted_at');
            });
        if ($cabangId) {
            $query->where('po.cabang_id', $cabangId);
        }

        return $query->select($select)->get()->all();
    }

    /** @param array<int, object> $rows */
    private function bucketize(array $rows, string $kolomTanggal): array
    {
        $count      = count($rows);
        $totalNilai = array_sum(array_map(fn ($r) => (float) $r->total_harga, $rows));
        $umurMaks   = 0;
        foreach ($rows as $r) {
            $tgl = $r->{$kolomTanggal} ?? null;
            if ($tgl) {
                $umur = (int) Carbon::parse($tgl)->diffInDays(now());
                if ($umur > $umurMaks) {
                    $umurMaks = $umur;
                }
            }
        }

        return [
            'count'          => $count,
            'total_nilai'    => $totalNilai,
            'umur_maks_hari' => $umurMaks,
            'badge_umur'     => $this->badgeUmur($umurMaks),
        ];
    }
}
