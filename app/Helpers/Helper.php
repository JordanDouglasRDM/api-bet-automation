<?php

namespace App\Helpers;

use Carbon\Carbon;

class Helper
{
    public static function getDaysBetweenDates(string $startDate, string $endDate): int
    {
        if ($startDate === $endDate) {
            return 1;
        }

        return Carbon::parse($startDate)
                ->diffInDays(Carbon::parse($endDate)) + 1;
    }

}