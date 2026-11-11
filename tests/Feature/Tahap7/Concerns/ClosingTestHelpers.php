<?php

namespace Tests\Feature\Tahap7\Concerns;

use App\Models\Cabang;
use App\Models\Kas;
use App\Models\KategoriTransaksi;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TransaksiKeuangan;
use App\Services\ClosingService;
use Carbon\Carbon;

/**
 * Helper bareng test Closing / Tutup Buku. Data dibuat langsung (tanggal
 * 2031, terisolasi dari data seeder). Class pemakai wajib mendefinisikan
 * konstanta TGL.
 */
trait ClosingTestHelpers
{
    protected int $seq = 0;

    protected function cabang(string $nama, string $tipe = 'cabang'): Cabang
    {
        return Cabang::create([
            'nama_cabang' => $nama,
            'kode_cabang' => 'CLS' . (++$this->seq) . rand(100, 999),
            'tipe'        => $tipe,
            'is_active'   => true,
        ]);
    }

    protected function kas(Cabang $cabang, string $nama, string $tipe = 'tunai'): Kas
    {
        return Kas::create([
            'cabang_id' => $cabang->id, 'nama_kas' => $nama, 'tipe_kas' => $tipe,
            'saldo_awal' => 0, 'saldo_sekarang' => 0, 'is_active' => true,
        ]);
    }

    protected function kat(string $kode): int
    {
        return (int) KategoriTransaksi::where('kode', $kode)->value('id');
    }

    /** Buat transaksi + update saldo kas, meniru efek controller/service asli. */
    protected function trx(Kas $kas, string $tipe, string $kode, float $jumlah, string $tanggal = self::TGL, array $extra = []): TransaksiKeuangan
    {
        $t = TransaksiKeuangan::create(array_merge([
            'cabang_id' => $kas->cabang_id, 'kas_id' => $kas->id,
            'nomor_transaksi' => 'CLS-' . uniqid() . (++$this->seq),
            'tanggal_transaksi' => $tanggal, 'tipe' => $tipe, 'kategori' => 'lainnya',
            'kategori_id' => $this->kat($kode), 'keterangan' => $kode, 'jumlah' => $jumlah,
        ], $extra));

        $pendingIn = $tipe === 'pemasukan' && ($extra['status_setoran'] ?? null) === 'menunggu_diterima';
        if (! $pendingIn) {
            $tipe === 'pemasukan' ? $kas->increment('saldo_sekarang', $jumlah) : $kas->decrement('saldo_sekarang', $jumlah);
        }

        return $t;
    }

    protected function order(Cabang $cabang, float $total, float $hpp, string $status = 'selesai', string $tanggal = self::TGL): Order
    {
        $o = Order::create([
            'cabang_id' => $cabang->id, 'nomor_order' => 'CLS-ORD-' . uniqid() . (++$this->seq),
            'tanggal_order' => $tanggal, 'tipe_order' => 'penjualan', 'tipe_transaksi' => 'takeaway',
            'status' => $status, 'nama_pelanggan' => 'Test', 'total_harga' => $total, 'diskon' => 0,
            'total_bayar' => $total, 'jumlah_bayar' => $total, 'kembalian' => 0, 'tipe_pembayaran' => 'tunai',
        ]);
        OrderItem::create([
            'order_id' => $o->id, 'nama_item' => 'Dimsum', 'qty' => 1, 'satuan' => 'pcs',
            'harga_satuan' => $total, 'total_harga' => $total, 'hpp' => $hpp,
        ]);

        return $o;
    }

    protected function hitung(?Cabang $cabang, string $dari = self::TGL, ?string $sampai = null): array
    {
        return app(ClosingService::class)->hitung(Carbon::parse($dari), Carbon::parse($sampai ?? $dari), $cabang?->id);
    }

    protected function mutasi(array $data, string $jenis): array
    {
        return collect($data['mutasi']['ringkasan'])->firstWhere('jenis', $jenis);
    }

    protected function saldoKas(array $data, Kas $kas): array
    {
        return collect($data['saldo_kas']['rows'])->firstWhere('kas_id', $kas->id);
    }
}
