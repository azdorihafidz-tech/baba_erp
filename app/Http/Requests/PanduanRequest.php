<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PanduanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('panduan.kelola'); // 4.43
    }

    public function rules(): array
    {
        $panduanId = $this->route('panduan')?->id;

        return [
            'slug'   => [
                'required', 'string', 'max:100',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('panduan', 'slug')->ignore($panduanId),
            ],
            'judul'  => 'required|string|max:200',
            'konten' => 'required|string',
            'modul'  => 'required|string|max:50',
            'urutan' => 'integer|min:0',
            'aktif'  => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug hanya boleh huruf kecil, angka, dan tanda hubung (-). Contoh: panduan-pos',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'aktif'  => $this->boolean('aktif'),
            'urutan' => (int) ($this->urutan ?? 0),
        ]);
    }
}
