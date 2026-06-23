<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Shop business week: Monday 09:00 through Saturday 23:59:59.
 * Sunday (and after Saturday close) shows the week that ended last Saturday night.
 */
final class BusinessWeek
{
    public static function startHour(): int
    {
        return (int) config('business.week_start_hour', 9);
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public static function containing(CarbonInterface $anchor): array
    {
        $anchor = Carbon::parse($anchor);
        $startHour = self::startHour();

        $mondayNine = $anchor->copy()
            ->startOfWeek(Carbon::MONDAY)
            ->setTime($startHour, 0, 0);

        $saturdayEnd = $mondayNine->copy()->addDays(5)->endOfDay();

        if ($anchor->lt($mondayNine)) {
            $mondayNine->subWeek();
            $saturdayEnd = $mondayNine->copy()->addDays(5)->endOfDay();
        } elseif ($anchor->gt($saturdayEnd)) {
            // Sunday (or after Saturday close): completed week ending last Saturday.
        }

        return [$mondayNine, $saturdayEnd];
    }
}
