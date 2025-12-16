<?php

declare(strict_types = 1);

namespace App\Http\Controllers;

use App\Http\Requests\CheckLicenseRequest;
use App\Http\Requests\DestroyBatchLicenseRequest;
use App\Http\Requests\StoreLicenseRequest;
use App\Http\Requests\UpdateLicenseRequest;
use App\Http\Utilities\ResponseFormatter;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {
    }

    public function store(StoreLicenseRequest $request): JsonResponse
    {
        $data                  = $request->validated();
        $serviceResponse       = $this->licenseService->store($data);
        $serviceResponse->data = null;

        return ResponseFormatter::format($serviceResponse);
    }
    public function destroy(Request $request, int $id): JsonResponse
    {
        $serviceResponse       = $this->licenseService->destroy($id);

        return ResponseFormatter::format($serviceResponse);
    }
    public function destroyBatch(DestroyBatchLicenseRequest $request): JsonResponse
    {
        $data                  = $request->validated();
        $serviceResponse       = $this->licenseService->destroyBatch($data);

        return ResponseFormatter::format($serviceResponse);
    }
    public function update(UpdateLicenseRequest $request): JsonResponse
    {
        $data                  = $request->validated();
        $serviceResponse       = $this->licenseService->update($data);
        $serviceResponse->data = null;

        return ResponseFormatter::format($serviceResponse);
    }
    public function index(Request $request): JsonResponse
    {
//        $data                  = $request->validated();
        $serviceResponse       = $this->licenseService->index();

        return ResponseFormatter::format($serviceResponse);
    }

    public function check(CheckLicenseRequest $request): JsonResponse
    {
        $data            = $request->validated();
        $serviceResponse = $this->licenseService->check($data);

        return ResponseFormatter::format($serviceResponse);
    }
}
