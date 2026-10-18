<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Rentang tanggal laporan.
 *
 * Definisi rentang (lihat docs/DECISIONS.md D-029):
 * - `from` dan `to` **inklusif**; satu tanggal berarti satu hari penuh.
 * - Bila tidak diisi, rentangnya berjalan dari awal bulan berjalan sampai
 *   hari ini (bukan 30 hari terakhir), supaya angka "bulan ini" mudah
 *   dibaca pemilik warung.
 */
final class ReportPeriod
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function currentMonth(): self
    {
        $today = CarbonImmutable::now()->startOfDay();

        return new self($today->startOfMonth(), $today);
    }

    /**
     * Baca `from`/`to` dari request. Validasi format dilakukan
     * `ReportRequest`; di sini nilai yang tidak terbaca jatuh ke default.
     */
    public static function fromRequest(Request $request): self
    {
        $default = self::currentMonth();

        return new self(
            self::parse($request->query('from')) ?? $default->from,
            self::parse($request->query('to')) ?? $default->to,
        );
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        // `createFromFormat()` menerima tanggal ngawur seperti 2026-13-45
        // dengan cara menggelindingkannya ke bulan berikutnya. Bandingkan
        // hasil cetaknya supaya tanggal seperti itu dianggap tidak terbaca.
        if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d') !== $value) {
            return null;
        }

        return $parsed;
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    /**
     * Rentang sebagai pasangan tanggal untuk `whereBetween` pada kolom DATE.
     *
     * @return array{0: string, 1: string}
     */
    public function dates(): array
    {
        return [$this->fromDate(), $this->toDate()];
    }

    public function label(): string
    {
        return $this->from->format('d/m/Y').' - '.$this->to->format('d/m/Y');
    }

    public function slug(): string
    {
        return $this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }

    public function dayCount(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }
}
