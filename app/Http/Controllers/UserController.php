<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Utilities\ResponseFormatter;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(protected UserService $userService)
    {
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serviceResponse = $this->userService->store($data);
        return ResponseFormatter::format($serviceResponse);
    }

    public function update(UpdateUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serviceResponse = $this->userService->update($data);
        return ResponseFormatter::format($serviceResponse);
    }

    public function index(IndexUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serviceResponse = $this->userService->index($data);
        return ResponseFormatter::format($serviceResponse);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $serviceResponse = $this->userService->destroy($id);
        return ResponseFormatter::format($serviceResponse);
    }
}
