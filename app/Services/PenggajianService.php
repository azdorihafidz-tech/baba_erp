<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\PengaturanGaji;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PenggajianService
{
    /**
     * Tarif lembur default dari PengaturanGaji; fallback ke formula gaji/173×1.5 jika tarif = 0.
     */
    public function hitungTarifLembur(float $gajiPokok): float
    {
        $tarif = PengaturanGaji::getSetting('tarif_lembur_per_jam', 0);
        return $tarif > 0 ? $tarif : round($gajiPokok / 173 * 1.5);
    }

    /**
     * Hitung PPh21 sederhana (flat progresif berdasarkan PKP setahun)
     * Tarif: 5% s/d 60jt, 15% 60-250jt, 25% 250-500jt, 30% >500jt
     */
    public function hitungPph21(float $gajiSetahun): float
    {
        $pkp = $gajiSetahun - 54000000; // PTKP TK/0
        if ($pkp <= 0) return 0;

        $pajak = 0;
        if ($pkp <= 60000000)       $pajak = $pkp * 0.05;
        elseif ($pkp <= 250000000)  $pajak = 3000000 + ($pkp - 60000000) * 0.15;
        elseif ($pkp <= 500000000)  $pajak = 31500000 + ($pkp - 250000000) * 0.25;
        else                         $pajak = 93500000 + ($pkp - 500000000) * 0.30;

        return round($pajak / 12); // per bulan
    }

    /**
     * Generate slip gaji untuk 1 karyawan pada periode tertentu
     */
    public function generate(Karyawan $karyawan, string $periode, array $override = []): Penggajian
    {
        return DB::transaction(function () use ($karyawan, $periode, $override) {
            [$tahun, $bulan] = explode('-', $periode);
            $tanggalAwal  = Carbon::create($tahun, $bulan, 1)->startOfMonth();
            $tanggalAkhir = $tanggalAwal->copy()->endOfMonth();

            // Hitung hari kerja menggunakan hari_kerja_per_minggu dan hari libur
            $hariKerjaPekan  = (int) (PengaturanGaji::getSetting('hari_kerja_per_minggu', 6) ?? 6);
            $cabangId        = $karyawan->cabang_id;
            $hariLiburSet    = HariLibur::getTanggalLibur(
                $tanggalAwal->format('Y-m-d'),
                $tanggalAkhir->format('Y-m-d'),
                $cabangId
            );

            $hariKerja = 0;
            $current   = $tanggalAwal->copy();
            while ($current <= $tanggalAkhir) {
                $dow = $current->dayOfWeek; // 0=Min, 6=Sab
                $adaLibur = isset($hariLiburSet[$current->format('Y-m-d')]);
                // Hari libur mingguan: Minggu selalu libur; Sabtu libur jika 5 hari/pekan
                $isHariLiburMingguan = ($dow === 0) || ($hariKerjaPekan <= 5 && $dow === 6);
                if (!$isHariLiburMingguan && !$adaLibur) {
                    $hariKerja++;
                }
                $current->addDay();
            }

            // Rekap absensi
            $absensis    = Absensi::where('karyawan_id', $karyawan->id)
                ->whereBetween('tanggal', [$tanggalAwal, $tanggalAkhir])->get();
            $jumlahHadir = $absensis->where('status', 'hadir')->count();
            $jumlahAlpha = $absensis->where('status', 'alpha')->count();
            $jamLembur   = $absensis->sum('jam_lembur');

            // ── PENAMBAHAN ────────────────────────────────────────────────
            $gajiPokok         = (float) $karyawan->gaji_pokok;
            $tunjanganJabatan  = (float) ($override['tunjangan_jabatan']  ?? $karyawan->tunjangan_jabatan  ?? 0);
            $tunjanganMakan    = (float) ($override['tunjangan_makan']    ?? $karyawan->tunjangan_makan    ?? 0);
            $tunjanganTransport= (float) ($override['tunjangan_transport'] ?? $karyawan->tunjangan_transport ?? 0);
            $tunjanganKehadiran= (float) ($override['tunjangan_kehadiran'] ?? 0);
            // Premi kehadiran: hanya dibayar jika hadir >= 80% hari kerja
            if ($tunjanganKehadiran == 0 && $hariKerja > 0 && ($jumlahHadir / $hariKerja) >= 0.8) {
                $tunjanganKehadiran = (float) ($override['tunjangan_kehadiran'] ?? 0);
            }
            // Tarif lembur: override dari array > setting global > formula
            $tarifLembur = isset($override['tarif_lembur_per_jam_override'])
                ? (float) $override['tarif_lembur_per_jam_override']
                : $this->hitungTarifLembur($gajiPokok);

            // Uang lembur: manual override masuk via override['uang_lembur']
            $uangLemburAuto   = round($jamLembur * $tarifLembur);
            $uangLemburManual = isset($override['uang_lembur']) && $override['uang_lembur'] !== '';
            $uangLembur       = $uangLemburManual ? (float) $override['uang_lembur'] : $uangLemburAuto;
            $bonus       = (float) ($override['bonus']   ?? 0);
            $insentif    = (float) ($override['insentif'] ?? 0);
            $thr         = (float) ($override['thr']     ?? 0);
            $komisi      = (float) ($override['komisi']  ?? 0);
            $tunjangan   = (float) ($override['tunjangan'] ?? 0); // tunjangan lain-lain

            // ── PENGURANGAN ───────────────────────────────────────────────
            $potonganAlpaSetting = PengaturanGaji::getSetting('potongan_alpa_per_hari', 0);
            $potonganAbsensiAuto = $potonganAlpaSetting > 0
                ? round($potonganAlpaSetting * $jumlahAlpha)
                : ($hariKerja > 0 ? round($gajiPokok / $hariKerja * $jumlahAlpha) : 0);

            $potonganAbsensiManual = isset($override['potongan_absensi']) && $override['potongan_absensi'] !== '';
            $potonganAbsensi = $potonganAbsensiManual
                ? (float) $override['potongan_absensi']
                : $potonganAbsensiAuto;

            // BPJS Kesehatan: % dari gaji pokok (ditanggung karyawan)
            $persenBpjsKes   = (float) ($karyawan->tunjangan_bpjs_kesehatan_persen ?? 1);
            $bpjsKesehatan   = (float) ($override['bpjs_kesehatan'] ?? round($gajiPokok * $persenBpjsKes / 100));

            // BPJS Ketenagakerjaan JHT: % dari gaji pokok
            $persenBpjsTk    = (float) ($karyawan->tunjangan_bpjs_tk_persen ?? 2);
            $bpjsTk          = (float) ($override['bpjs_ketenagakerjaan'] ?? round($gajiPokok * $persenBpjsTk / 100));

            // PPh21: dihitung dari total penghasilan bruto setahun
            $brutoBulan = $gajiPokok + $tunjanganJabatan + $tunjanganMakan + $tunjanganTransport + $uangLembur + $bonus + $insentif + $tunjangan;
            $pph21      = (float) ($override['pph21'] ?? $this->hitungPph21($brutoBulan * 12));

            $kasbon      = (float) ($override['kasbon']       ?? 0);
            $potonganLain= (float) ($override['potongan_lain'] ?? 0);

            // ── TOTAL ─────────────────────────────────────────────────────
            $totalPenambahan  = $gajiPokok + $tunjanganJabatan + $tunjanganMakan + $tunjanganTransport
                + $tunjanganKehadiran + $uangLembur + $bonus + $insentif + $thr + $komisi + $tunjangan;
            $totalPotongan    = $potonganAbsensi + $bpjsKesehatan + $bpjsTk + $pph21 + $kasbon + $potonganLain;
            $totalGaji        = max(0, $totalPenambahan - $totalPotongan);

            return Penggajian::updateOrCreate(
                ['karyawan_id' => $karyawan->id, 'periode' => $periode],
                [
                    'cabang_id'            => $karyawan->cabang_id,
                    'jumlah_hari_kerja'    => $hariKerja,
                    'jumlah_hadir'         => $jumlahHadir,
                    'jumlah_alpha'         => $jumlahAlpha,
                    'jam_lembur_total'     => $jamLembur,
                    'gaji_pokok'           => $gajiPokok,
                    'tunjangan_jabatan'    => $tunjanganJabatan,
                    'tunjangan_makan'      => $tunjanganMakan,
                    'tunjangan_transport'  => $tunjanganTransport,
                    'tunjangan_kehadiran'  => $tunjanganKehadiran,
                    'tunjangan'            => $tunjangan,
                    'uang_lembur'          => $uangLembur,
                    'bonus'                => $bonus,
                    'insentif'             => $insentif,
                    'thr'                  => $thr,
                    'komisi'               => $komisi,
                    'potongan_absensi'     => $potonganAbsensi,
                    'bpjs_kesehatan'       => $bpjsKesehatan,
                    'bpjs_ketenagakerjaan' => $bpjsTk,
                    'pph21'                => $pph21,
                    'kasbon'               => $kasbon,
                    'potongan_lain'        => $potonganLain,
                    'total_gaji'                     => $totalGaji,
                    'tarif_lembur_per_jam_override'  => $uangLemburManual ? $tarifLembur : null,
                    'potongan_alpa_per_hari_override' => $potonganAbsensiManual ? ($jumlahAlpha > 0 ? round($potonganAbsensi / $jumlahAlpha) : null) : null,
                    'uang_lembur_manual'             => $uangLemburManual,
                    'potongan_alpa_manual'           => $potonganAbsensiManual,
                    'status'                         => 'draft',
                ]
            );
        });
    }

    /**
     * Bulk generate untuk semua karyawan aktif di cabang
     */
    public function bulkGenerate(int $cabangId, string $periode, array $override = []): int
    {
        $karyawans = Karyawan::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('cabang_id', $cabangId)
            ->where('status', 'aktif')
            ->get();

        $count = 0;
        foreach ($karyawans as $k) {
            $this->generate($k, $periode, $override);
            $count++;
        }

        return $count;
    }
}
