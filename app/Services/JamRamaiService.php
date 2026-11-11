<?php

namespace App\Services;

use App\Enums\StatusOrder;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Analisa Jam Ramai (Peak Hours) — murni dari `orders.created_at` (kapan
 * kasir benar-benar memproses transaksi pelanggan), TIDAK dari
 * `transaksi_keuangans`. Alasan (lihat audit sesi ini): 58,9% baris
 * transaksi_keuangans di-input belakangan (tanggal_transaksi != tanggal
 * created_at) dan 19,8% auto-generated dari batch Auto Depresiasi — kalau
 * dipakai untuk "jam ramai", hasilnya menyesatkan (bukan traffic pelanggan
 * real). `orders.created_at` 100% valid & real-time (dikonfirmasi saat
 * audit — 0 dari 14 order NULL, timestamp bervariasi wajar).
 *
 * Murni READ, nol perubahan ke PenjualanService/data historis.
 */
class JamRamaiService
{
    public function getAnalisaJamRamai(Carbon $mulai, Carbon $akhir, ?int $cabangId = null): array
    {
        $baseQuery = Order::withoutGlobalScopes()
            ->penjualanSah()
            ->whereBetween('tanggal_order', [$mulai->toDateString(), $akhir->toDateString()]);
        if ($cabangId) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        $jumlahHariOperasional = (clone $baseQuery)->distinct('tanggal_order')->count('tanggal_order');

        $rows = (clone $baseQuery)
            ->selectRaw('HOUR(created_at) as jam, COUNT(*) as jumlah, SUM(total_bayar) as total_nominal')
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->get()
            ->keyBy('jam');

        $perJam = [];
        for ($h = 0; $h < 24; $h++) {
            $row = $rows->get($h);
            $perJam[] = [
                'jam' => $h,
                'label' => sprintf('%02d:00', $h),
                'jumlah_transaksi' => $row ? (int) $row->jumlah : 0,
                'total_nominal' => $row ? (float) $row->total_nominal : 0.0,
            ];
        }

        $totalTransaksi = array_sum(array_column($perJam, 'jumlah_transaksi'));
        $totalNominal = array_sum(array_column($perJam, 'total_nominal'));

        $jamAktif = array_values(array_filter($perJam, fn ($j) => $j['jumlah_transaksi'] > 0));

        $jamPuncak = null;
        $jamSepi = null;
        if (!empty($jamAktif)) {
            $jamPuncak = collect($jamAktif)->sortByDesc('jumlah_transaksi')->first();
            $jamSepi = collect($jamAktif)->sortBy('jumlah_transaksi')->first();
        }

        return [
            'periode' => ['mulai' => $mulai->toDateString(), 'akhir' => $akhir->toDateString()],
            'per_jam' => $perJam,
            'jam_puncak' => $jamPuncak,
            'jam_sepi' => ($jamSepi && $jamPuncak && $jamSepi['jam'] === $jamPuncak['jam']) ? null : $jamSepi,
            'total_transaksi' => $totalTransaksi,
            'total_nominal' => $totalNominal,
            'jumlah_jam_aktif' => count($jamAktif),
            'jumlah_hari_operasional' => $jumlahHariOperasional,
            'rekomendasi' => $this->generateRekomendasi($jamPuncak, $jamSepi, $totalTransaksi, count($jamAktif), $jumlahHariOperasional),
        ];
    }

    /**
     * Rekomendasi data-driven — dibangun dari kondisi angka aktual (jam
     * puncak/sepi, jumlah data), bukan template statis.
     */
    private function generateRekomendasi(?array $jamPuncak, ?array $jamSepi, int $totalTransaksi, int $jumlahJamAktif, int $jumlahHariOperasional): array
    {
        $rekomendasi = [];

        if ($totalTransaksi === 0) {
            $rekomendasi[] = 'Tidak ada transaksi pada periode ini — belum bisa dianalisa.';
            return $rekomendasi;
        }

        if ($jamPuncak) {
            $persen = round($jamPuncak['jumlah_transaksi'] / $totalTransaksi * 100, 1);
            $rekomendasi[] = sprintf(
                'Jam %s paling ramai dengan %d transaksi (%s%% dari total) — pertimbangkan tambah staff atau siapkan stok lebih banyak menjelang jam ini.',
                $jamPuncak['label'],
                $jamPuncak['jumlah_transaksi'],
                number_format($persen, 1, ',', '.')
            );
        }

        if ($jamSepi && $jamPuncak && $jamSepi['jam'] !== $jamPuncak['jam']) {
            $rekomendasi[] = sprintf(
                'Jam %s relatif paling sepi (%d transaksi) di antara jam-jam yang sudah ada aktivitas — cocok untuk jadwalkan promo/diskon guna menarik pelanggan di jam ini.',
                $jamSepi['label'],
                $jamSepi['jumlah_transaksi']
            );
        }

        if ($jumlahJamAktif <= 5 || $totalTransaksi < 30) {
            $rekomendasi[] = sprintf(
                'Data baru mencakup %d transaksi di %d hari operasional (%d jam berbeda) — pola jam ramai belum solid secara statistik. Kumpulkan lebih banyak data sebelum mengambil keputusan besar (mis. perubahan jadwal staff permanen).',
                $totalTransaksi,
                $jumlahHariOperasional,
                $jumlahJamAktif
            );
        }

        return $rekomendasi;
    }
}
