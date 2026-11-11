<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TooltipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('panduan.kelola'); // 4.43
    }

    public function rules(): array
    {
        $tooltipId = $this->route('tooltip')?->id;

        return [
            'key'     => ['required', 'string', 'max:100',
                          Rule::unique('tooltips', 'key')->ignore($tooltipId)],
            'title'   => 'nullable|string|max:100',
            'content' => 'required|string|max:500',
            'modul'   => 'required|string|max:50',
            'aktif'   => 'boolean',
            'urutan'  => 'integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'key.required'    => 'Key tooltip wajib diisi.',
            'key.unique'      => 'Key ini sudah digunakan tooltip lain.',
            'key.max'         => 'Key maksimal 100 karakter.',
            'content.required'=> 'Isi konten tooltip wajib diisi.',
            'content.max'     => 'Konten maksimal 500 karakter.',
            'modul.required'  => 'Modul wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'aktif'   => $this->boolean('aktif'),
            'urutan'  => (int) ($this->urutan ?? 0),
        ]);
    }
}
