<?php

namespace App\Enums;

enum TipePembayaran: string
{
    case Tunai = 'tunai';
    case Transfer = 'transfer';
    case Qris = 'qris';

    public function label(): string
    {
        return match($this) {
            TipePembayaran::Tunai => 'Tunai',
            TipePembayaran::Transfer => 'Transfer Bank',
            TipePembayaran::Qris => 'QRIS',
        };
    }

    /**
     * Kategori Kas yang menampung settlement metode ini.
     *
     * Riwayat (2026-09-17, dihapus): sempat ada case Gojek/Grab yang SENGAJA
     * tidak dapat Kas kategori sendiri — uangnya cair ke rekening bank via
     * transfer, jadi settle ke Kas kategori "transfer" outlet yang sama
     * seperti transfer bank biasa. Case-nya dihapus (keputusan Owner,
     * D'mentai fokus retail walk-in bukan food delivery; kalau ada order
     * Gojek/Grab, settlement dicatat manual sebagai "Transfer" saja) — tapi
     * method ini dipertahankan strukturnya (match ke $this->value) supaya
     * kalau nanti dibutuhkan lagi tinggal tambah case + 1 baris match di sini.
     */
    public function kasKategori(): string
    {
        return $this->value;
    }
}
