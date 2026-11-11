<?php

namespace App\Support;

use App\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CLAUDE.md 4.43 — Rapikan Role & Hak Akses: setiap menu punya centangan
 * sendiri dan centangan itu benar-benar berlaku.
 *
 * IZIN_BARU   : izin yang ditambahkan.
 * WARISAN     : izin diberikan sekali (lewat migration) ke role yang DULU
 *               sudah bisa melihat sekaligus membuka menunya, supaya tidak
 *               ada yang kehilangan akses. Aturan (semua harus terpenuhi):
 *                 'semua'        => role punya semua izin ini
 *                 'role'         => role termasuk daftar role sistem ini
 *                 'semua_cabang' => role boleh akses semua cabang
 * LABEL       : ganti nama tampilan izin lama (tidak dipakai / khusus Owner).
 * IZIN_LAPORAN: bagian "Laporan" di sidebar tampil kalau punya salah satunya.
 */
class HakAksesMenu
{
    public const IZIN_BARU = [
        ['name' => 'laporan.penjualan.view',        'display_name' => 'Lihat Laporan Penjualan',                       'group' => 'laporan'],
        ['name' => 'laporan.stok.view',             'display_name' => 'Lihat Laporan Stok (Stok, Pergerakan, Minimum)', 'group' => 'laporan'],
        ['name' => 'laporan.keuangan.view',         'display_name' => 'Lihat Laporan Keuangan (Laba Rugi & Arus Kas)', 'group' => 'laporan'],
        ['name' => 'laporan.transfer_dana.view',    'display_name' => 'Lihat Laporan Transfer Dana',                   'group' => 'laporan'],
        ['name' => 'laporan.kategori.view',         'display_name' => 'Lihat Laporan Per Kategori',                    'group' => 'laporan'],
        ['name' => 'laporan.audit_bukti.view',      'display_name' => 'Lihat Laporan Audit Bukti',                     'group' => 'laporan'],
        ['name' => 'laporan.saldo_kas.view',        'display_name' => 'Lihat Laporan Saldo Kas',                       'group' => 'laporan'],
        ['name' => 'laporan.hr_absensi.view',       'display_name' => 'Lihat Laporan HR — Absensi',                    'group' => 'laporan'],
        ['name' => 'laporan.hr_penggajian.view',    'display_name' => 'Lihat Laporan HR — Penggajian',                 'group' => 'laporan'],
        ['name' => 'laporan.hr_evaluasi.view',      'display_name' => 'Lihat Laporan HR — Evaluasi',                   'group' => 'laporan'],
        ['name' => 'laporan.aset.view',             'display_name' => 'Lihat Laporan Aset',                            'group' => 'laporan'],
        ['name' => 'laporan.bep.view',              'display_name' => 'Lihat Laporan BEP',                             'group' => 'laporan'],
        ['name' => 'laporan.cabang_vs_cabang.view', 'display_name' => 'Lihat Laporan Cabang vs Cabang',                'group' => 'laporan'],
        ['name' => 'supplier.view',                 'display_name' => 'Lihat Supplier',                                'group' => 'pembelian'],
        ['name' => 'supplier.manage',               'display_name' => 'Kelola Supplier (tambah, ubah, nonaktifkan)',   'group' => 'pembelian'],
        ['name' => 'absensi.device',                'display_name' => 'Kelola Device Absensi',                         'group' => 'hr'],
        ['name' => 'panduan.kelola',                'display_name' => 'Kelola Isi Panduan & Tooltip',                  'group' => 'pengaturan'],
    ];

    public const WARISAN = [
        'laporan.penjualan.view'        => ['semua' => ['laporan.view', 'order.view']],
        'laporan.stok.view'             => ['semua' => ['laporan.view', 'stok.view']],
        'laporan.keuangan.view'         => ['semua' => ['laporan.view', 'keuangan.view']],
        'laporan.transfer_dana.view'    => ['semua' => ['laporan.view', 'keuangan.view']],
        'laporan.kategori.view'         => ['semua' => ['laporan.view', 'keuangan.view']],
        'laporan.audit_bukti.view'      => ['semua' => ['laporan.view', 'keuangan.view']],
        'laporan.saldo_kas.view'        => ['semua' => ['laporan.view', 'keuangan.view']],
        'laporan.hr_absensi.view'       => ['semua' => ['laporan.view', 'absensi.view']],
        'laporan.hr_penggajian.view'    => ['semua' => ['laporan.view', 'penggajian.view']],
        'laporan.hr_evaluasi.view'      => ['semua' => ['laporan.view', 'evaluasi.view']],
        'laporan.aset.view'             => ['semua' => ['laporan.view', 'aset.view']],
        'laporan.bep.view'              => ['semua' => ['laporan.view', 'bep.view']],
        'laporan.cabang_vs_cabang.view' => ['semua' => ['laporan.view'], 'semua_cabang' => true],

        // Tahap 2: menu yang dulu dikunci per role (role = daftar role yang dulu diizinkan)
        'cabang.create'     => ['semua' => ['cabang.view'], 'role' => ['admin_pusat']],
        'cabang.edit'       => ['semua' => ['cabang.view'], 'role' => ['admin_pusat']],
        'cabang.delete'     => ['semua' => ['cabang.view'], 'role' => ['admin_pusat']],
        'user.create'       => ['semua' => ['user.view'], 'role' => ['admin_pusat', 'manajer_cabang']],
        'user.edit'         => ['semua' => ['user.view'], 'role' => ['admin_pusat', 'manajer_cabang']],
        'user.delete'       => ['semua' => ['user.view'], 'role' => ['admin_pusat', 'manajer_cabang']],
        'supplier.view'     => ['semua' => ['pembelian.view'], 'role' => ['admin_pusat', 'admin_gudang']],
        'supplier.manage'   => ['semua' => ['pembelian.view'], 'role' => ['admin_pusat', 'admin_gudang']],
        'absensi.manage'    => ['semua' => ['absensi.view'], 'role' => ['admin_pusat', 'manajer_cabang', 'admin_gudang']],
        'absensi.device'    => ['semua' => ['absensi.view'], 'role' => ['admin_pusat', 'manajer_cabang']],
        'penggajian.manage' => ['semua' => ['penggajian.view'], 'role' => ['admin_pusat', 'manajer_cabang']],
        'evaluasi.manage'   => ['semua' => ['evaluasi.view'], 'role' => ['admin_pusat', 'manajer_cabang']],
        'panduan.kelola'    => ['role' => ['admin_pusat']],
    ];

    public const LABEL = [
        'laporan.view' => 'Lihat Laporan (lama — tidak dipakai lagi)',
        'pengaturan.umum_lihat' => 'Lihat Pengaturan Umum (khusus Owner — centangan tidak berpengaruh)',
        'pengaturan.umum_edit'  => 'Edit Pengaturan Umum (khusus Owner — centangan tidak berpengaruh)',
    ];

    /** Tahap 4: izin yang tidak dipakai halaman mana pun — labelnya diberi akhiran SUFIKS_TIDAK_DIPAKAI. */
    public const TIDAK_DIPAKAI = [
        'coa.manage', 'export_audit_log', 'item.delete', 'lihat_shift',
        'master.item_varian.view', 'master.item_varian.create', 'master.item_varian.edit', 'master.item_varian.delete',
        'pemakaian_perlengkapan.delete', 'pemakaian_perlengkapan.export',
    ];

    public const SUFIKS_TIDAK_DIPAKAI = ' (tidak dipakai — centangan tidak berpengaruh)';

    public const IZIN_LAPORAN = [
        'laporan.penjualan.view', 'laporan.stok.view', 'laporan.keuangan.view', 'laporan.hr_absensi.view',
        'laporan.hr_penggajian.view', 'laporan.hr_evaluasi.view', 'laporan.aset.view', 'laporan.bep.view',
        'laporan.bep_otomatis.view', 'laporan.transfer_dana.view', 'laporan.kategori.view', 'laporan.audit_bukti.view',
        'laporan.saldo_kas.view', 'laporan.setoran_harian.view', 'laporan.konsumsi_bahan.view', 'laporan.laba_rugi.view',
        'laporan.laba_rugi_formal.view', 'laporan.neraca.view', 'laporan.buku_besar.view', 'laporan.eksekutif.view',
        'laporan.simulator.view', 'laporan.jam_ramai.view', 'laporan.perlengkapan.view', 'laporan.setoran_kasir.view',
        'laporan.cabang_vs_cabang.view',
    ];

    /**
     * Buat izin baru + ganti label + beri centang warisan (insertOrIgnore,
     * centang yang sudah ada tidak disentuh).
     *
     * @return array{izin_dibuat: array<int,int>, centang: array<int,array{role:string,permission_id:int}>, label_lama: array<string,string>}
     */
    public static function terapkan(): array
    {
        $hasil = ['izin_dibuat' => [], 'centang' => [], 'label_lama' => []];

        foreach (self::IZIN_BARU as $data) {
            $p = Permission::firstOrCreate(['name' => $data['name']], $data);
            if ($p->wasRecentlyCreated) {
                $hasil['izin_dibuat'][] = $p->id;
            }
        }

        $labels = self::LABEL;
        foreach (Permission::whereIn('name', self::TIDAK_DIPAKAI)->get() as $p) {
            if (! str_contains($p->display_name, 'tidak dipakai')) {
                $labels[$p->name] = $p->display_name . self::SUFIKS_TIDAK_DIPAKAI;
            }
        }

        foreach ($labels as $name => $label) {
            $p = Permission::where('name', $name)->first();
            if ($p && $p->display_name !== $label) {
                $hasil['label_lama'][$name] = $p->display_name;
                $p->update(['display_name' => $label]);
            }
        }

        $izinId = Permission::pluck('id', 'name');
        $punya = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->get(['role_permissions.role', 'permissions.name'])
            ->groupBy('role')->map(fn ($rows) => $rows->pluck('name')->all());
        $semuaRole = collect(Roles::values(false))->merge($punya->keys())->unique();

        foreach (self::WARISAN as $izin => $aturan) {
            if (! isset($izinId[$izin])) {
                continue;
            }
            foreach ($semuaRole as $role) {
                $milik = $punya[$role] ?? [];
                if (array_diff($aturan['semua'] ?? [], $milik)) {
                    continue;
                }
                if (isset($aturan['role']) && ! in_array($role, $aturan['role'], true)) {
                    continue;
                }
                if (! empty($aturan['semua_cabang']) && ! Roles::find($role)?->canAccessAllBranches()) {
                    continue;
                }
                if (in_array($izin, $milik, true)) {
                    continue;
                }
                $baris = ['role' => $role, 'permission_id' => $izinId[$izin]];
                DB::table('role_permissions')->insertOrIgnore($baris);
                $hasil['centang'][] = $baris;
            }
        }

        self::lupakanCache();

        return $hasil;
    }

    public static function lupakanCache(): void
    {
        Cache::forget('all_permission_names');
        foreach (DB::table('role_permissions')->distinct()->pluck('role')->merge(Roles::values()) as $role) {
            Cache::forget("role_permissions_{$role}");
        }
    }
}
