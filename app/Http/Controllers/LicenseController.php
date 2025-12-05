<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLicenseRequest;
use App\Http\Requests\CheckLicenseRequest;
use App\Http\Utilities\ResponseFormatter;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;

class LicenseController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    )
    {
    }

    public function store(StoreLicenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serviceResponse = $this->licenseService->store($data);
        return ResponseFormatter::format($serviceResponse);
    }
    public function check(CheckLicenseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serviceResponse = $this->licenseService->check($data);
        return ResponseFormatter::format($serviceResponse);
    }
}
