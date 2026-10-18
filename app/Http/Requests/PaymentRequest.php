<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    /**
     * Otorisasi ditangani policy dokumennya (SalePolicy/PurchasePolicy)
     * di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Batas atas pembayaran tidak divalidasi di sini karena bergantung
     * pada saldo dokumen; PaymentService yang menolak kelebihan bayar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => Money::parseToDecimal($this->input('amount')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Jumlah pembayaran wajib diisi.',
            'amount.numeric' => 'Jumlah pembayaran harus berupa angka.',
            'amount.gt' => 'Jumlah pembayaran harus lebih dari nol.',
            'paid_at.required' => 'Tanggal pembayaran wajib diisi.',
            'paid_at.date' => 'Tanggal pembayaran tidak valid.',
        ];
    }
}
