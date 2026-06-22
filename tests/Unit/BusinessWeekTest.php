<?php

namespace Tests\Unit;

use App\Support\BusinessWeek;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BusinessWeekTest extends TestCase
{
    #[DataProvider('containingProvider')]
    public function test_containing_returns_monday_nine_to_friday_end(
        string $anchor,
        string $expectedFrom,
        string $expectedTo,
    ): void {
        [$from, $to] = BusinessWeek::containing(Carbon::parse($anchor));

        $this->assertSame($expectedFrom, $from->format('Y-m-d H:i:s'));
        $this->assertSame($expectedTo, $to->format('Y-m-d H:i:s'));
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function containingProvider(): array
    {
        return [
            'wednesday midday' => ['2026-06-18 12:00:00', '2026-06-15 09:00:00', '2026-06-19 23:59:59'],
            'monday before nine uses previous week' => ['2026-06-15 08:30:00', '2026-06-08 09:00:00', '2026-06-12 23:59:59'],
            'monday after nine starts current week' => ['2026-06-15 10:00:00', '2026-06-15 09:00:00', '2026-06-19 23:59:59'],
            'saturday shows completed week' => ['2026-06-20 10:00:00', '2026-06-15 09:00:00', '2026-06-19 23:59:59'],
            'sunday shows completed week' => ['2026-06-21 15:00:00', '2026-06-15 09:00:00', '2026-06-19 23:59:59'],
            'friday night still in week' => ['2026-06-19 23:30:00', '2026-06-15 09:00:00', '2026-06-19 23:59:59'],
        ];
    }
}
