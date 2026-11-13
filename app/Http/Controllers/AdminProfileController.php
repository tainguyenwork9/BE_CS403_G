<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileStatusRequest;
use App\Services\AdminProfileService;

class AdminProfileController extends Controller
{
    public function __construct(
        private readonly AdminProfileService $adminProfileService
    ) {}

    public function getProfiles()
    {
        $result = $this->adminProfileService->getProfiles();
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function getPendingProfiles()
    {
        $result = $this->adminProfileService->getPendingProfiles();
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function updateStatus(UpdateProfileStatusRequest $request)
    {
        $validated = $request->validated();
        $result = $this->adminProfileService->updateStatus(
            (int) $validated['maHoSo'],
            $validated['action']
        );
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }
}
