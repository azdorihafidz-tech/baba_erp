<?php

namespace App\Services;

use App\Models\LoyaltyPencapaian;
use App\Models\LoyaltyProgram;
use App\Models\Pelanggan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Loyalty Program (Fase 1) — tracking otomatis kumulatif progress per
 * pelanggan. Murni READ untuk perhitungan progress, WRITE cuma di
 * cekPencapaianBaru() (insert loyalty_pencapaian, idempotent).
 *
 * Tahap 7 D'mentai (2026-09-17) — extend basis dari SELALU
 * orders.berat_daging_kg (lini jasa giling) jadi 3 pilihan via
 * `loyalty_programs.sumber_data`:
 *  - orders.berat_daging_kg (legacy, kg — jasa giling)
 *  - orders.total_bayar (Rp — total belanja pelanggan)
 *  - orders.count (jumlah transaksi/order)
 * Nama key return TETAP `total_kg`/`target_kg` (konsisten prinsip yang sama
 * dengan BepOtomatisService — label historis, bukan satuan literal; cek
 * `LoyaltyProgram::satuan_qty` untuk tahu satuan SEBENARNYA saat
 * menampilkan ke user, JANGAN hardcode " kg" di view).
 */
class LoyaltyService
{
    /**
     * Kolom + fungsi agregat sesuai basis program. `kolom=null` berarti
     * agregat COUNT(*) (basis jumlah transaksi, tidak butuh SUM kolom apapun).
     */
    private function resolveAgregat(LoyaltyProgram $program): array
    {
        return match ($program->sumber_data) {
            'orders.total_bayar' => ['kolom' => 'total_bayar', 'fungsi' => 'sum'],
            'orders.count' => ['kolom' => null, 'fungsi' => 'count'],
            default => ['kolom' => 'berat_daging_kg', 'fungsi' => 'sum'], // orders.berat_daging_kg (legacy)
        };
    }

    /**
     * Total qty pelanggan untuk 1 program, sesuai tipe_item + periode
     * program (null periode_mulai/akhir = all-time) + basis sumber_data.
     */
    public function hitungTotalQty(int $pelangganId, LoyaltyProgram $program): float
    {
        $query = DB::table('orders')
            ->where('pelanggan_id', $pelangganId)
            ->where('tipe_order', $program->tipe_item)
            ->whereNotIn('status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('deleted_at')
            ->whereNull('deleted_at');

        if ($program->periode_mulai) {
            $query->whereDate('tanggal_order', '>=', $program->periode_mulai);
        }
        if ($program->periode_akhir) {
            $query->whereDate('tanggal_order', '<=', $program->periode_akhir);
        }

        $agregat = $this->resolveAgregat($program);

        return $agregat['fungsi'] === 'count'
            ? (float) $query->count()
            : (float) $query->sum($agregat['kolom']);
    }

    /**
     * Progress 1 pelanggan pada 1 program.
     * Return: total_kg, target_kg, persen_progress, tercapai (bool),
     * jumlah_order_tanpa_data (caveat: order dgn kolom basis NULL/0,
     * dianggap 0 -- lihat catatan audit Fase 1A, TIDAK ditebak. Basis
     * 'orders.count' tidak punya konsep "tanpa data", selalu 0).
     */
    public function hitungProgressPelanggan(int $pelangganId, int $programId): array
    {
        $program = LoyaltyProgram::findOrFail($programId);
        $totalKg = $this->hitungTotalQty($pelangganId, $program);
        $targetKg = (float) $program->target_qty_kg;
        $persen = $targetKg > 0 ? round(min(100, ($totalKg / $targetKg) * 100), 1) : 0;

        $agregat = $this->resolveAgregat($program);
        $jumlahOrderTanpaData = 0;
        if ($agregat['fungsi'] === 'sum') {
            $jumlahOrderTanpaData = DB::table('orders')
                ->where('pelanggan_id', $pelangganId)
                ->where('tipe_order', $program->tipe_item)
                ->whereNotIn('status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('deleted_at')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($agregat) {
                    $q->whereNull($agregat['kolom'])->orWhere($agregat['kolom'], 0);
                })
                ->count();
        }

        return [
            'program'                  => $program,
            'total_kg'                 => $totalKg,
            'target_kg'                => $targetKg,
            'persen_progress'          => $persen,
            'tercapai'                 => $totalKg >= $targetKg,
            'jumlah_order_tanpa_data'  => $jumlahOrderTanpaData,
        ];
    }

    /**
     * Progress SEMUA pelanggan (yang pernah order sesuai tipe_item program)
     * untuk 1 program — dipakai halaman Detail Program & widget dashboard.
     * Diurutkan dari progress tertinggi.
     */
    public function hitungProgressSemuaPelanggan(int $programId): Collection
    {
        $program = LoyaltyProgram::findOrFail($programId);
        $agregat = $this->resolveAgregat($program);

        $totalExpr = $agregat['fungsi'] === 'count'
            ? 'COUNT(orders.id)'
            : "SUM(orders.{$agregat['kolom']})";
        $tanpaDataExpr = $agregat['fungsi'] === 'count'
            ? '0'
            : "SUM(CASE WHEN orders.{$agregat['kolom']} IS NULL OR orders.{$agregat['kolom']} = 0 THEN 1 ELSE 0 END)";

        $totals = DB::table('orders')
            ->join('pelanggans', 'pelanggans.id', '=', 'orders.pelanggan_id')
            ->where('orders.tipe_order', $program->tipe_item)
            ->whereNotIn('orders.status', \App\Enums\StatusOrder::bukanPenjualan())->whereNull('orders.deleted_at')
            ->whereNull('orders.deleted_at')
            ->whereNull('pelanggans.deleted_at')
            ->when($program->periode_mulai, fn ($q) => $q->whereDate('orders.tanggal_order', '>=', $program->periode_mulai))
            ->when($program->periode_akhir, fn ($q) => $q->whereDate('orders.tanggal_order', '<=', $program->periode_akhir))
            ->select(
                'pelanggans.id as pelanggan_id',
                'pelanggans.nama_pelanggan',
                DB::raw("{$totalExpr} as total_kg"),
                DB::raw("{$tanpaDataExpr} as jumlah_order_tanpa_data")
            )
            ->groupBy('pelanggans.id', 'pelanggans.nama_pelanggan')
            ->orderByDesc('total_kg')
            ->get();

        $targetKg = (float) $program->target_qty_kg;

        return $totals->map(function ($row) use ($targetKg, $program) {
            $totalKg = (float) $row->total_kg;
            $persen = $targetKg > 0 ? round(min(100, ($totalKg / $targetKg) * 100), 1) : 0;

            $sudahHadiah = LoyaltyPencapaian::where('pelanggan_id', $row->pelanggan_id)
                ->where('loyalty_program_id', $program->id)
                ->where('status', 'hadiah_diberikan')
                ->exists();

            return [
                'pelanggan_id'            => $row->pelanggan_id,
                'jumlah_order_tanpa_data' => (int) $row->jumlah_order_tanpa_data,
                'nama_pelanggan'   => $row->nama_pelanggan,
                'total_kg'         => $totalKg,
                'target_kg'        => $targetKg,
                'persen_progress'  => $persen,
                'tercapai'         => $totalKg >= $targetKg,
                'hadiah_diberikan' => $sudahHadiah,
            ];
        });
    }

    /**
     * Data widget Dashboard "Pelanggan Loyalty Progress" — pelanggan dengan
     * progress >= 80% ke target (lintas SEMUA program aktif, digabung 1
     * list), max $maxRows baris, diurutkan progress tertinggi. Loyalty
     * SENGAJA tidak di-scope per cabang (kumulatif pelanggan lintas cabang
     * by design — sama seperti field sumbernya, orders.berat_daging_kg,
     * tidak pernah difilter cabang di service ini).
     */
    public function getWidgetData(int $maxRows = 10): array
    {
        $mendekatiTarget = collect();

        // Fase 2 nambah tipe_program event_based (target_qty_kg=0, klaim
        // manual bukan progress kg) — widget "mendekati target" ini murni
        // konsep auto_track, jadi scope dari awal (bukan cuma andalkan guard
        // target>0 di hitungProgressSemuaPelanggan yang bikin persen selalu 0).
        foreach (LoyaltyProgram::aktif()->autoTrack()->get() as $program) {
            $progressSemua = $this->hitungProgressSemuaPelanggan($program->id)
                ->filter(fn ($row) => $row['persen_progress'] >= 80 && !$row['hadiah_diberikan'])
                ->map(fn ($row) => array_merge($row, ['program_nama' => $program->nama]));

            $mendekatiTarget = $mendekatiTarget->concat($progressSemua);
        }

        $jumlahTercapaiBelumHadiah = LoyaltyPencapaian::where('status', 'tercapai')->count();

        return [
            'pelanggan_mendekati_target' => $mendekatiTarget->sortByDesc('persen_progress')->take($maxRows)->values(),
            'jumlah_tercapai_belum_hadiah' => $jumlahTercapaiBelumHadiah,
        ];
    }

    /**
     * Cek pelanggan yang BARU melintasi threshold target untuk semua
     * program aktif — insert loyalty_pencapaian idempotent. Dipanggil dari
     * scheduled command (loyalty:cek-pencapaian) atau trigger manual.
     *
     * Non-berulang: maksimal 1 record 'tercapai'/'hadiah_diberikan' per
     * pelanggan+program — skip kalau sudah ada.
     * Berulang: boleh banyak record, 1 per kelipatan target yang terlewati
     * (mis. 500kg, 1000kg, 1500kg masing-masing 1 record) — dihitung dari
     * jumlah record existing vs floor(total_kg / target_kg).
     *
     * Return: array pencapaian baru yang di-insert (utk notifikasi/log).
     */
    public function cekPencapaianBaru(): array
    {
        $baru = [];

        // WAJIB scope autoTrack() — event_based (target_qty_kg=0) kalau ikut
        // loop ini: (a) $row['tercapai'] SELALU true (totalKg>=0 target),
        // pencapaian palsu ke-generate utk semua pelanggan yang order jasa
        // giling; (b) kalau program->berulang=true, floor($totalKg / 0) di
        // bawah bikin DivisionByZeroError. Event_based punya jalur sendiri
        // (LoyaltyKlaim + LoyaltyKlaimService), tidak lewat pipeline ini.
        foreach (LoyaltyProgram::aktif()->autoTrack()->get() as $program) {
            $progressSemua = $this->hitungProgressSemuaPelanggan($program->id);

            foreach ($progressSemua as $row) {
                if ($row['total_kg'] <= 0) {
                    continue;
                }

                $existingCount = LoyaltyPencapaian::where('pelanggan_id', $row['pelanggan_id'])
                    ->where('loyalty_program_id', $program->id)
                    ->count();

                $targetKelipatanTercapai = $program->berulang
                    ? (int) floor($row['total_kg'] / $row['target_kg'])
                    : ($row['tercapai'] ? 1 : 0);

                if ($targetKelipatanTercapai <= $existingCount) {
                    continue; // belum ada kelipatan baru yang terlewati
                }

                for ($i = $existingCount; $i < $targetKelipatanTercapai; $i++) {
                    $pencapaian = LoyaltyPencapaian::create([
                        'pelanggan_id'       => $row['pelanggan_id'],
                        'loyalty_program_id' => $program->id,
                        'tanggal_tercapai'   => now()->toDateString(),
                        'progress_kg'        => $row['total_kg'],
                        'status'             => 'tercapai',
                    ]);
                    $baru[] = $pencapaian;
                }
            }
        }

        return $baru;
    }
}
