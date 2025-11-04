<?php

declare(strict_types = 1);

namespace App\Services;

use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class DeviceTokenService
{
    public static function generate(string $deviceId, ?string $userAgent, string $ip): string
    {
        $claims = [
            'device_id'  => $deviceId,
            'user_agent' => $userAgent,
            'ip'         => $ip,
            'type'       => 'device_token',
        ];

        $payload = JWTFactory::customClaims($claims)
            ->setTTL(60 * 24 * 7) // 7 dias
            ->make();

        return JWTAuth::encode($payload)->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $token): ?array
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();

            return [
                'device_id'  => $payload->get('device_id'),
                'user_agent' => $payload->get('user_agent'),
                'ip'         => $payload->get('ip'),
            ];
        } catch (JWTException) {
            return null;
        }
    }
}
