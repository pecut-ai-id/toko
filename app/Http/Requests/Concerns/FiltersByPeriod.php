<?php

namespace App\Http\Requests\Concerns;

use App\Support\ReportPeriod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Filter rentang tanggal untuk halaman yang menampilkan data per periode
 * (laporan dan riwayat aktivitas).
 *
 * Aturannya ditaruh di satu tempat supaya pesan kesalahan dan perilaku
 * saat gagal tidak berbeda antar halaman:
 * - satu tanggal berarti satu hari penuh (inklusif);
 * - default = awal bulan berjalan sampai hari ini;
 * - saat gagal, pengguna dikembalikan ke halaman itu sendiri, bukan lewat
 *   `back()` yang bergantung pada Referer/sesi.
 */
trait FiltersByPeriod
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function periodRules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function periodMessages(): array
    {
        return [
            'from.date_format' => 'Tanggal mulai harus berformat YYYY-MM-DD.',
            'to.date_format' => 'Tanggal akhir harus berformat YYYY-MM-DD.',
        ];
    }

    /**
     * Urutan tanggal diperiksa setelah formatnya benar, dan hanya bila
     * kedua tanggal memang diisi --- supaya mengisi satu tanggal saja
     * tidak pernah menjadi kesalahan yang tidak bisa diperbaiki pengguna.
     *
     * @return array<int, callable>
     */
    protected function periodAfter(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('from') || $validator->errors()->has('to')) {
                    return;
                }

                $from = $this->input('from');
                $to = $this->input('to');

                if (filled($from) && filled($to) && (string) $to < (string) $from) {
                    $validator->errors()->add(
                        'to',
                        'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.'
                    );
                }
            },
        ];
    }

    /**
     * `redirectTo()` dipakai (bukan response jadi) supaya pesan
     * kesalahannya tetap ikut di-flash ke session.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw (new ValidationException($validator))->redirectTo(url()->current());
    }

    /**
     * Rentang tanggal yang diminta; default = awal bulan berjalan sampai
     * hari ini.
     */
    public function period(): ReportPeriod
    {
        return ReportPeriod::fromRequest($this);
    }
}
