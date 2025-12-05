<?php

declare(strict_types = 1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\LogoutAuthRequest;
use App\Http\Utilities\ResponseFormatter;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
    }

    /**
     * Autentica e gera um access_token + refresh_token.
     */
    public function login(LoginUserRequest $request): JsonResponse
    {
        $credentials     = $request->validated();
        $serviceResponse = $this->authService->login($credentials, $request);

        return ResponseFormatter::formatWithCookie($serviceResponse);
    }

    /**
     * Retorna os dados do usuário autenticado.
     */
    public function logged(): JsonResponse
    {
        $serviceResponse = $this->authService->logged();

        return ResponseFormatter::format($serviceResponse);
    }

    /**
     * Faz logout e revoga o refresh token.
     */
    public function logout(LogoutAuthRequest $request): JsonResponse
    {
        $data            = $request->validated();
        $serviceResponse = $this->authService->logout($data, $request);

        return ResponseFormatter::format($serviceResponse);
    }

    /**
     * Gera um novo access token a partir de um refresh token válido.
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $serviceResponse = $this->authService->refreshToken($request);

        return ResponseFormatter::format($serviceResponse);
    }
}
