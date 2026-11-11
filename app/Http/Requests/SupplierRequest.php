<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('supplier')?->id;
        return [
            'nama_supplier' => 'required|string|max:255',
            'kode_supplier' => 'required|string|max:20|unique:suppliers,kode_supplier,' . $id,
            'kontak_person' => 'nullable|string|max:255',
            'telepon'       => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:255',
            'alamat'        => 'nullable|string',
            'kota'          => 'nullable|string|max:100',
            'cabang_id'     => 'nullable|exists:cabangs,id', // Tahap C 4.38 — supplier internal
            'catatan'       => 'nullable|string',
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_supplier' => 'Nama Supplier',
            'kode_supplier' => 'Kode Supplier',
            'kontak_person' => 'Kontak Person',
            'telepon'       => 'Telepon',
            'email'         => 'Email',
            'alamat'        => 'Alamat',
            'kota'          => 'Kota',
        ];
    }
}
