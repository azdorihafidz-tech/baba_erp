<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\StokOpnamePengingatNotification;
use App\Services\DashboardStokOpnameService;
use App\Services\NotificationService;
use App\Services\StokOpnameService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * ERP BABA Tahap 7e — pengingat stok opname akhir bulan (H-3 s.d. hari terakhir, terjadwal harian 08:00).
 * Lokasi ber-stok yang bulan ini belum punya opname (berjalan / selesai) → lonceng ke kepala lokasi (izin ajukan, akun lokasi itu)
 * + Owner untuk lokasi BABA; toko mitra → orang toko itu sendiri. Satu lonceng per orang per hari (idempotent).
 */
class PengingatStokOpname extends Command
{
    protected $signature = 'stok-opname:pengingat {--tanggal= : uji tanggal tertentu (Y-m-d)} {--paksa : kirim walau di luar masa pengingat}';

    protected $description = 'Ingatkan lokasi yang belum stok opname menjelang akhir bulan';

    public function handle(DashboardStokOpnameService $dash): int
    {
        $hari = $this->option('tanggal') ? Carbon::parse($this->option('tanggal')) : now();
        if (! $this->option('paksa') && ! DashboardStokOpnameService::masaPengingat($hari)) {
            $this->info('Belum masa pengingat (mulai H-' . \App\Services\JadwalOtomatisService::hariPengingat() . ' akhir bulan).');

            return self::SUCCESS;
        }
        $bulan = $hari->translatedFormat('F Y');
        $belum = collect($dash->statusBulanIni(DashboardStokOpnameService::lokasiBerstok(), $hari))->where('status', 'belum')->pluck('lokasi');
        if ($belum->isEmpty()) {
            $this->info('Semua lokasi sudah stok opname.');

            return self::SUCCESS;
        }
        $penerima = [];
        $users = User::where('is_active', true)->with('cabangs')->get();
        foreach ($belum as $c) {
            $mitra = StokOpnameService::lokasiMitra($c);
            foreach ($users as $u) {
                $kepala = ! $u->canAccessAllBranches() && $u->cabangs->contains('id', $c->id) && $u->can($mitra ? 'stok_opname.hitung' : 'stok_opname.ajukan');
                $owner = ! $mitra && $u->role === \App\Enums\RoleUser::Owner;
                if ($kepala || $owner) {
                    $penerima[$u->id]['user'] = $u;
                    $penerima[$u->id]['lokasi'][] = $c->nama_cabang;
                }
            }
        }
        $kirim = 0;
        foreach ($penerima as $p) {
            $sudah = $p['user']->notifications()->where('type', StokOpnamePengingatNotification::class)->whereDate('created_at', $hari->toDateString())->exists();
            if (! $sudah) {
                NotificationService::send([$p['user']], new StokOpnamePengingatNotification($p['lokasi'], $bulan));
                $kirim++;
            }
        }
        $this->info("Lokasi belum opname: {$belum->count()}; pengingat terkirim ke {$kirim} orang.");

        return self::SUCCESS;
    }
}
