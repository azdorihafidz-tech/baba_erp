<?php

namespace App\Enums;

enum RoleUser: string
{
    case Owner = 'owner';
    case AdminPusat = 'admin_pusat';
    case AdminGudang = 'admin_gudang';
    case ManajerCabang = 'manajer_cabang';
    case Kasir = 'kasir';
    case OperatorProduksi = 'operator_produksi';
    case Helper = 'helper';
    /** Tim IT penyedia aplikasi — tersembunyi dari pelanggan (porting Dimsum 4.53, _rancangan/RANCANGAN-KAMUS-TIMIT-PAKET.md). */
    case Teknisi = 'teknisi';

    public function label(): string
    {
        return match($this) {
            RoleUser::Owner => 'Owner',
            RoleUser::AdminPusat => 'Admin Pusat',
            RoleUser::AdminGudang => 'Admin Gudang Pusat',
            RoleUser::ManajerCabang => 'Manajer Cabang',
            RoleUser::Kasir => 'Kasir',
            RoleUser::OperatorProduksi => 'Operator Produksi',
            RoleUser::Helper => 'Helper',
            RoleUser::Teknisi => 'Tim IT',
        };
    }

    public function canAccessAllBranches(): bool
    {
        return in_array($this, [self::Owner, self::AdminPusat, self::Teknisi]);
    }
}
