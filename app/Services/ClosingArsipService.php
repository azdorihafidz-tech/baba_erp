<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\Closing;
use App\Models\ClosingKas;
use App\Support\ClosingLock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Closing / Tutup Buku — simpan snapshot (yang sekaligus mengunci periode)
 * dan buka kunci. Angka diambil dari ClosingService::hitung() lalu
 * dibekukan apa adanya di kolom JSON `closings.data`.
 *
 * Aturan:
 * - Closing harian: 1 hari 1 cabang, tidak boleh tanggal yang akan datang,
 *   wajib hitung uang fisik tiap kas tunai (selisih fisik − sistem dicatat).
 * - Tutup buku bulanan: periode harus sudah berakhir (boleh hari terakhirnya).
 * - Keseluruhan: hanya setelah SEMUA cabang aktif tutup buku bulanan untuk
 *   periode yang sama; tidak mengunci apa pun sendiri.
 * - Buka kunci (Owner): status → dibuka, riwayat tetap. Membuka tutup buku
 *   bulanan ikut membuka closing harian di dalamnya + closing keseluruhan
 *   periode itu, supaya periodenya benar-benar bisa dikoreksi.
 */
class ClosingArsipService
{
    public function __construct(private ClosingService $closingService)
    {
    }

    /**
     * @param array<int|string, mixed> $saldoFisik [kas_id => jumlah uang fisik]
     */
    public function simpanHarian(int $cabangId, Carbon $tanggal, int $userId, array $saldoFisik, ?string $catatan = null): Closing
    {
        $tanggal = $tanggal->copy()->startOfDay();
        if ($tanggal->gt(Carbon::today())) {
            throw new \DomainException('Belum bisa closing tanggal yang akan datang.');
        }

        return DB::transaction(function () use ($cabangId, $tanggal, $userId, $saldoFisik, $catatan) {
            Cabang::whereKey($cabangId)->lockForUpdate()->first();

            if ($kunci = ClosingLock::closingYangMengunci($cabangId, $tanggal)) {
                throw new \DomainException("Tanggal {$tanggal->format('d/m/Y')} sudah di-closing ({$kunci->jenis_label} {$kunci->label_periode}).");
            }

            $data = $this->closingService->hitung($tanggal, $tanggal, $cabangId);
            $label = $tanggal->copy()->locale('id')->translatedFormat('l, d F Y');

            return $this->simpan('harian', $cabangId, $tanggal, $tanggal, $label, $data, $userId, $catatan, $saldoFisik);
        });
    }

    public function simpanBulanan(int $cabangId, string $bulan, int $userId, ?string $catatan = null): Closing
    {
        $periode = $this->closingService->resolvePeriode('bulanan', null, $bulan);
        $this->pastikanPeriodeBerakhir($periode['end']);

        return DB::transaction(function () use ($cabangId, $periode, $userId, $catatan) {
            Cabang::whereKey($cabangId)->lockForUpdate()->first();

            $bentrok = Closing::final()->where('jenis', 'bulanan')->where('cabang_id', $cabangId)
                ->whereDate('periode_mulai', '<=', $periode['end']->toDateString())
                ->whereDate('periode_selesai', '>=', $periode['start']->toDateString())
                ->first();
            if ($bentrok) {
                throw new \DomainException("Periode ini sudah tutup buku ({$bentrok->label_periode}).");
            }

            $data = $this->closingService->hitung($periode['start'], $periode['end'], $cabangId);

            return $this->simpan('bulanan', $cabangId, $periode['start'], $periode['end'], $periode['label'], $data, $userId, $catatan);
        });
    }

    public function simpanKeseluruhan(string $bulan, int $userId, ?string $catatan = null): Closing
    {
        $periode = $this->closingService->resolvePeriode('bulanan', null, $bulan);
        $this->pastikanPeriodeBerakhir($periode['end']);

        $belum = $this->cabangBelumTutupBuku($periode['start'], $periode['end']);
        if ($belum->isNotEmpty()) {
            throw new \DomainException('Cabang berikut belum tutup buku bulanan periode ini: ' . $belum->pluck('nama_cabang')->implode(', ') . '.');
        }

        return DB::transaction(function () use ($periode, $userId, $catatan) {
            $sudah = Closing::final()->where('jenis', 'bulanan')->whereNull('cabang_id')
                ->whereDate('periode_mulai', $periode['start']->toDateString())
                ->whereDate('periode_selesai', $periode['end']->toDateString())
                ->lockForUpdate()->exists();
            if ($sudah) {
                throw new \DomainException('Tutup buku keseluruhan periode ini sudah dibuat.');
            }

            $data = $this->closingService->hitung($periode['start'], $periode['end'], null);

            return $this->simpan('bulanan', null, $periode['start'], $periode['end'], $periode['label'], $data, $userId, $catatan);
        });
    }

    /** Cabang aktif yang belum punya tutup buku bulanan final tepat pada periode ini. */
    public function cabangBelumTutupBuku(Carbon $start, Carbon $end)
    {
        $sudah = Closing::final()->where('jenis', 'bulanan')->whereNotNull('cabang_id')
            ->whereDate('periode_mulai', $start->toDateString())
            ->whereDate('periode_selesai', $end->toDateString())
            ->pluck('cabang_id');

        return Cabang::aktif()->whereNotIn('id', $sudah)->orderBy('id')->get(['id', 'nama_cabang']);
    }

    public function bukaKunci(Closing $closing, int $userId, string $alasan): Closing
    {
        if (! $closing->isFinal()) {
            throw new \DomainException('Closing ini sudah dibuka sebelumnya.');
        }

        if ($closing->jenis === 'harian') {
            $bulanan = Closing::final()->where('jenis', 'bulanan')->where('cabang_id', $closing->cabang_id)
                ->whereDate('periode_mulai', '<=', $closing->periode_mulai->toDateString())
                ->whereDate('periode_selesai', '>=', $closing->periode_mulai->toDateString())
                ->first();
            if ($bulanan) {
                throw new \DomainException("Tanggal ini juga terkunci oleh Tutup Buku Bulanan {$bulanan->label_periode}. Buka tutup buku bulanan itu terlebih dahulu.");
            }
        }

        return DB::transaction(function () use ($closing, $userId, $alasan) {
            $tandai = fn (Closing $c, string $a) => $c->update([
                'status' => Closing::STATUS_DIBUKA, 'dibuka_oleh' => $userId, 'dibuka_pada' => now(), 'alasan_buka' => $a,
            ]);

            $tandai($closing, $alasan);

            $ikut = collect();
            if ($closing->jenis === 'bulanan' && $closing->cabang_id) {
                // Closing harian di dalam periode ikut dibuka.
                $ikut = Closing::final()->where('jenis', 'harian')->where('cabang_id', $closing->cabang_id)
                    ->whereDate('periode_mulai', '>=', $closing->periode_mulai->toDateString())
                    ->whereDate('periode_selesai', '<=', $closing->periode_selesai->toDateString())
                    ->get();
            }
            if ($closing->cabang_id) {
                // Closing keseluruhan yang mencakup periode ini jadi tidak valid.
                $ikut = $ikut->merge(Closing::final()->whereNull('cabang_id')
                    ->whereDate('periode_mulai', '<=', $closing->periode_selesai->toDateString())
                    ->whereDate('periode_selesai', '>=', $closing->periode_mulai->toDateString())
                    ->get());
            }
            foreach ($ikut as $c) {
                $tandai($c, "Ikut dibuka bersama {$closing->jenis_label} {$closing->nama_cabang} {$closing->label_periode}: {$alasan}");
            }

            activity('Closing')
                ->performedOn($closing)
                ->causedBy($userId)
                ->withProperties(['alasan' => $alasan, 'ikut_dibuka' => $ikut->pluck('id')->all()])
                ->log("Buka kunci {$closing->jenis_label} {$closing->nama_cabang} {$closing->label_periode} (versi {$closing->versi})");

            return $closing->fresh();
        });
    }

    private function pastikanPeriodeBerakhir(Carbon $end): void
    {
        if ($end->gt(Carbon::today())) {
            throw new \DomainException('Periode ini belum berakhir (berakhir ' . $end->format('d/m/Y') . '). Tutup buku bisa dilakukan mulai hari terakhir periode.');
        }
    }

    private function simpan(string $jenis, ?int $cabangId, Carbon $start, Carbon $end, string $label, array $data, int $userId, ?string $catatan, array $saldoFisik = []): Closing
    {
        $versi = 1 + (int) Closing::where('jenis', $jenis)
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId), fn ($q) => $q->whereNull('cabang_id'))
            ->whereDate('periode_mulai', $start->toDateString())
            ->whereDate('periode_selesai', $end->toDateString())
            ->max('versi');

        $kasRows = [];
        $totalSelisih = null;
        foreach ($data['saldo_kas']['rows'] as $r) {
            $fisik = null;
            $selisih = null;
            if ($jenis === 'harian' && $r['grup'] === 'tunai' && array_key_exists($r['kas_id'], $saldoFisik)) {
                $fisik = (float) $saldoFisik[$r['kas_id']];
                $selisih = round($fisik - (float) $r['saldo_akhir'], 2);
                $totalSelisih = ($totalSelisih ?? 0) + $selisih;
            }
            $kasRows[] = [
                'kas_id' => $r['kas_id'], 'nama_kas' => $r['nama_kas'], 'nama_cabang' => $r['cabang'], 'grup' => $r['grup'],
                'saldo_awal' => $r['saldo_awal'], 'masuk' => $r['masuk'], 'keluar' => $r['keluar'],
                'mutasi_masuk' => $r['mutasi_masuk'], 'mutasi_keluar' => $r['mutasi_keluar'], 'saldo_akhir' => $r['saldo_akhir'],
                'saldo_fisik' => $fisik, 'selisih' => $selisih,
            ];
        }

        $closing = Closing::create([
            'jenis'           => $jenis,
            'cabang_id'       => $cabangId,
            'periode_mulai'   => $start->toDateString(),
            'periode_selesai' => $end->toDateString(),
            'label_periode'   => $label,
            'versi'           => $versi,
            'status'          => Closing::STATUS_FINAL,
            'omzet'           => $data['penjualan']['omzet_bersih'],
            'hpp'             => $data['laba_rugi']['hpp'],
            'laba_bersih'     => $data['laba_rugi']['laba_bersih'],
            'saldo_kas_akhir' => $data['saldo_kas']['total']['saldo_akhir'],
            'nilai_stok'      => $data['stok']['total'],
            'total_selisih_fisik' => $totalSelisih,
            'data'            => $data,
            'catatan'         => $catatan,
            'ditutup_oleh'    => $userId,
            'ditutup_pada'    => now(),
        ]);

        foreach ($kasRows as $row) {
            ClosingKas::create($row + ['closing_id' => $closing->id]);
        }

        activity('Closing')
            ->performedOn($closing)
            ->causedBy($userId)
            ->log("{$closing->jenis_label} {$closing->nama_cabang} {$label} (versi {$versi})");

        return $closing;
    }
}
