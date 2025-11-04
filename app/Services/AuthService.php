<?php

declare(strict_types = 1);

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use App\Http\Utilities\ServiceResponse;
use App\Models\RefreshToken;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Throwable;

class AuthService
{
    /**
     * @param array<string, mixed> $credentials
     */
    public function login(array $credentials, Request $request): ServiceResponse
    {
        try {
            if (! $token = auth()->attempt($credentials)) {
                throw new UnauthorizedException('Usuário ou senha inválidos.');
            }

            /** @var Authenticatable $user */
            $user = auth()->user();

            $deviceId      = Uuid::uuid4()->toString();
            $tokenUnhashed = Uuid::uuid4()->toString();

            RefreshToken::create([
                'user_id'    => $user->getAuthIdentifier(),
                'token'      => hash('sha256', $tokenUnhashed),
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_id'  => $deviceId,
                'expires_at' => now()->addDays(7),
            ]);

            $deviceToken = DeviceTokenService::generate(
                deviceId: $deviceId,
                userAgent: $request->userAgent(),
                ip: $request->ip()
            );

            $model = [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'device_token' => $deviceToken,
                'expires_at'   => auth()->factory()->getTTL() * 60,
            ];

            $cookie = [
                'name'     => 'refresh_token',
                'value'    => $tokenUnhashed,
                'minutes'  => 60 * 24 * 7,
                'path'     => '/',
                'domain'   => null,
                'secure'   => app()->isProduction(),
                'httpOnly' => true,
                'raw'      => false,
                'sameSite' => 'Strict',
            ];

            return ServiceResponse::success(
                data: $model,
                message: 'Sucesso ao gerar tokens para o usuário.',
                cookie: $cookie
            );
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 401);
        } catch (Throwable $e) {
            return ServiceResponse::error($e);
        }
    }

    public function logged(): ServiceResponse
    {
        try {
            if (! auth()->check()) {
                throw new UnauthorizedException();
            }

            $user = auth()->user();

            return ServiceResponse::success($user, 'Usuário autenticado recuperado.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 401);
        } catch (Throwable $e) {
            return ServiceResponse::error($e);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function logout(array $data, Request $request): ServiceResponse
    {
        try {
            if (! auth()->check()) {
                throw new UnauthorizedException();
            }

            $deviceToken = $data['device_token'] ?? null;

            if (($data['revoke_all_devices'] ?? false) === true) {
                RefreshToken::where('user_id', auth()->id())
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
            }

            $payload = $deviceToken ? DeviceTokenService::decode($deviceToken) : null;

            if ($payload && isset($payload['device_id'])) {
                RefreshToken::where('user_id', auth()->id())
                    ->where('device_id', $payload['device_id'])
                    ->update(['revoked_at' => now()]);
            } else {
                RefreshToken::where('user_id', auth()->id())
                    ->where('user_agent', $request->userAgent())
                    ->update(['revoked_at' => now()]);
            }

            auth()->logout();

            return ServiceResponse::success(message: 'Logout realizado com sucesso.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 401);
        } catch (Throwable $e) {
            return ServiceResponse::error($e);
        }
    }

    public function refreshToken(Request $request): ServiceResponse
    {
        try {
            $refreshToken = $request->cookie('refresh_token');

            if (! is_string($refreshToken)) {
                throw new UnauthorizedException('Refresh token não encontrado.');
            }

            $refresh = RefreshToken::where('token', hash('sha256', $refreshToken))->first();

            if (! $refresh || ! $refresh->isValid()) {
                throw new UnauthorizedException('Refresh token inválido');
            }

            if ($refresh->anotherDevice($request)) {
                $refresh->update(['revoked_at' => now()]);

                throw new UnauthorizedException(
                    'Refresh token revogado por uso em dispositivo diferente do emissor.'
                );
            }

            $user = $refresh->user;

            if (! $user instanceof Authenticatable) {
                throw new UnauthorizedException('Usuário inválido.');
            }

            $accessToken = auth()->login($user);

            $model = [
                'access_token' => $accessToken,
                'token_type'   => 'bearer',
                'expires_at'   => auth()->factory()->getTTL() * 60,
            ];

            return ServiceResponse::success(data: $model, message: 'Novos tokens gerados.');
        } catch (UnauthorizedException $e) {
            return ServiceResponse::error($e, 401);
        } catch (Throwable $e) {
            return ServiceResponse::error($e);
        }
    }
}
