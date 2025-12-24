<?php

namespace App\Helpers;

use App\Models\License;
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

    public static function getDaysLeftToExpires(License $license, bool $formatted): int|string
    {
        if (!$license->expires_at) return '-';

        $expires = Carbon::parse($license->expires_at)->endOfDay();
        $today = Carbon::now()->endOfDay();
        $days = (int)$today->diffInDays($expires) + 1;
        if ($formatted) return self::formatDaysToExpires($days);
        return $days;
    }

    private static function formatDaysToExpires(int $days): string
    {
        if ($days <= 0) return 'Expirada';
        if ($days === 1) return '1 dia';
        return $days . ' dias';
    }

}