<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi payload snapshot Simulator BEP (Sprint 3 lanjutan, 2026-09-22).
 * Otorisasi sudah digate via middleware route `can:laporan.simulator.export`.
 */
class SimulatorBepSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_simulasi'   => ['nullable', 'string', 'max:150'],
            'cabang_id'       => ['nullable', 'integer'],
            'volume_harian'   => ['required', 'numeric', 'min:0'],
            'harga_jual'      => ['required', 'numeric', 'min:0'],
            'biaya_variabel'  => ['required', 'numeric', 'min:0'],
            'beban_tetap'     => ['required', 'numeric', 'min:0'],
            'modal_awal'      => ['nullable', 'numeric', 'min:0'],
            'target_profit'   => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'volume_harian.required'  => 'Volume harian wajib diisi.',
            'volume_harian.numeric'   => 'Volume harian harus berupa angka.',
            'volume_harian.min'       => 'Volume harian tidak boleh negatif.',
            'harga_jual.required'     => 'Harga jual wajib diisi.',
            'harga_jual.numeric'      => 'Harga jual harus berupa angka.',
            'harga_jual.min'          => 'Harga jual tidak boleh negatif.',
            'biaya_variabel.required' => 'Biaya variabel wajib diisi.',
            'biaya_variabel.numeric'  => 'Biaya variabel harus berupa angka.',
            'biaya_variabel.min'      => 'Biaya variabel tidak boleh negatif.',
            'beban_tetap.required'    => 'Beban tetap wajib diisi.',
            'beban_tetap.numeric'     => 'Beban tetap harus berupa angka.',
            'beban_tetap.min'         => 'Beban tetap tidak boleh negatif.',
        ];
    }
}
