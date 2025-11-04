<?php

declare(strict_types = 1);

namespace App\Services;

use Jenssegers\Agent\Agent;

class DeviceService
{
    public static function getDeviceName(?string $userAgent): string
    {
        $agent = new Agent();
        $agent->setUserAgent($userAgent !== null && $userAgent !== '' && $userAgent !== '0' ? $userAgent : 'unknown');

        $device      = $agent->device() ?: '';
        $deviceEmpty = ':';

        if (empty($device)) {
            $deviceEmpty = '';
        }
        $platform = $agent->platform() ?: 'Unknown OS';
        $browser  = $agent->browser() ?: 'Unknown Browser';

        return trim("{$device}{$deviceEmpty} {$platform} - {$browser}");
    }
}
