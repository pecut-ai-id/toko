<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentVoidRequest extends FormRequest
{
    /**
     * Otorisasi ditangani PaymentPolicy di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Alasan wajib: membatalkan pembayaran mengubah saldo piutang/utang,
     * jadi jejak alasannya harus ada (D-015).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembatalan pembayaran wajib diisi.',
            'reason.min' => 'Alasan pembatalan minimal 3 karakter.',
        ];
    }
}
