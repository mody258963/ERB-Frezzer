<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Shop business week: Monday 09:00 through Friday 23:59:59.
 * Saturday/Sunday show the week that ended the previous Friday night.
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

        $fridayEnd = $mondayNine->copy()->addDays(4)->endOfDay();

        if ($anchor->lt($mondayNine)) {
            $mondayNine->subWeek();
            $fridayEnd = $mondayNine->copy()->addDays(4)->endOfDay();
        } elseif ($anchor->gt($fridayEnd)) {
            // Saturday/Sunday (or after Friday close): completed week ending last Friday.
        }

        return [$mondayNine, $fridayEnd];
    }
}
