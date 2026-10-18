<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    /**
     * Otorisasi ditangani ProductPolicy di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kuantitas memakai format angka Indonesia yang sama dengan form lain,
     * dinormalkan sebelum validasi (D-012).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => Money::parseToDecimal($this->input('quantity')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'direction.required' => 'Arah penyesuaian wajib dipilih.',
            'direction.in' => 'Arah penyesuaian harus masuk atau keluar.',
            'quantity.required' => 'Jumlah penyesuaian wajib diisi.',
            'quantity.numeric' => 'Jumlah penyesuaian harus berupa angka.',
            'quantity.gt' => 'Jumlah penyesuaian harus lebih dari nol.',
            'reason.required' => 'Alasan penyesuaian wajib diisi.',
            'reason.min' => 'Alasan penyesuaian minimal 3 karakter.',
            'occurred_at.date' => 'Tanggal penyesuaian tidak valid.',
        ];
    }
}
