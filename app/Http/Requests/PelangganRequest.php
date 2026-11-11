<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PelangganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('pelanggan')?->id;
        return [
            'nama_pelanggan' => 'required|string|max:255',
            'kode_pelanggan' => 'required|string|max:20|unique:pelanggans,kode_pelanggan,' . $id,
            'telepon'        => 'nullable|string|max:20',
            'email'          => 'nullable|email',
            'alamat'         => 'nullable|string',
            'kota'           => 'nullable|string|max:100',
            'catatan'        => 'nullable|string',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_pelanggan' => 'Nama Pelanggan',
            'kode_pelanggan' => 'Kode Pelanggan',
            'telepon'        => 'Telepon',
            'email'          => 'Email',
            'alamat'         => 'Alamat',
            'kota'           => 'Kota',
        ];
    }
}
