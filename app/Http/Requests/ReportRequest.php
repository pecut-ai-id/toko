<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FiltersByPeriod;
use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    use FiltersByPeriod;

    /**
     * Otorisasi ditangani policy model yang dilaporkan di controller
     * (mis. SalePolicy untuk laporan penjualan).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->periodRules(),
            'product_id' => ['nullable', 'integer'],
            'format' => ['nullable', 'in:csv'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->periodMessages(),
            'product_id.integer' => 'Filter produk tidak valid.',
            'format.in' => 'Format ekspor tidak dikenal.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->periodAfter();
    }

    public function productId(): ?int
    {
        $value = $this->query('product_id');

        return is_numeric($value) ? (int) $value : null;
    }

    public function wantsCsv(): bool
    {
        return $this->input('format') === 'csv';
    }
}
