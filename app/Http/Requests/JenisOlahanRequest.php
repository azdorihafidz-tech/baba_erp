<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JenisOlahanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $jenisOlahanId = $this->route('jenis_olahan')?->id;

        return [
            'nama'      => ['required', 'string', 'max:50', Rule::unique('jenis_olahans', 'nama')->ignore($jenisOlahanId)],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama jenis olahan wajib diisi.',
            'nama.max'      => 'Nama maksimal 50 karakter.',
            'nama.unique'   => 'Nama jenis olahan ini sudah ada.',
        ];
    }
}
