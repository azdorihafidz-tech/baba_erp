<?php

namespace App\Http\Requests;

use App\Enums\KategoriTransaksi;
use App\Enums\TipeTransaksiKeuangan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransaksiKeuanganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_transaksi' => ['required', 'date'],
            'tipe'              => ['required', Rule::in(array_column(TipeTransaksiKeuangan::cases(), 'value'))],
            'kategori'          => ['required', Rule::in(array_column(KategoriTransaksi::cases(), 'value'))],
            'keterangan'        => ['required', 'string', 'max:255'],
            'jumlah'            => ['required', 'numeric', 'min:1'],
            'kas_id'            => ['nullable', 'exists:kas,id'],
            'catatan'           => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_transaksi.required' => 'Tanggal transaksi wajib diisi.',
            'tanggal_transaksi.date'     => 'Format tanggal tidak valid.',
            'tipe.required'              => 'Tipe transaksi wajib dipilih.',
            'tipe.in'                    => 'Tipe transaksi tidak valid.',
            'kategori.required'          => 'Kategori wajib dipilih.',
            'kategori.in'                => 'Kategori tidak valid.',
            'keterangan.required'        => 'Keterangan wajib diisi.',
            'keterangan.max'             => 'Keterangan maksimal 255 karakter.',
            'jumlah.required'            => 'Jumlah wajib diisi.',
            'jumlah.numeric'             => 'Jumlah harus berupa angka.',
            'jumlah.min'                 => 'Jumlah minimal 1.',
            'kas_id.exists'              => 'Kas yang dipilih tidak ditemukan.',
        ];
    }
}
