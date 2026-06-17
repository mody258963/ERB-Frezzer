<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class DashboardPeriod
{
    public const DAY = 'day';

    public const WEEK = 'week';

    public const MONTH = 'month';

    public function __construct(
        public readonly string $key,
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly string $anchorDate,
    ) {}

    public static function fromRequest(?string $period, ?string $date = null): self
    {
        $key = in_array($period, [self::DAY, self::WEEK, self::MONTH], true) ? $period : self::WEEK;
        $anchor = $date !== null && $date !== ''
            ? Carbon::parse($date)
            : now();

        [$from, $to] = match ($key) {
            self::DAY => [$anchor->copy()->startOfDay(), $anchor->copy()->endOfDay()],
            self::MONTH => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
        };

        return new self($key, $from, $to, $anchor->toDateString());
    }

    public function cacheSuffix(): string
    {
        return $this->key.'.'.$this->anchorDate;
    }

    /**
     * @return array{key: string, from: string, to: string, anchor_date: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
            'anchor_date' => $this->anchorDate,
        ];
    }
}
