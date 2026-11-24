<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadProfileDocsRequest;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    public function getProfile(Request $request)
    {
        $result = $this->profileService->getProfile($request->user());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $result = $this->profileService->updateProfile($request->user(), $request->validated());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function uploadDocs(UploadProfileDocsRequest $request)
    {
        $result = $this->profileService->uploadDocs(
            $request->user(),
            $request->validated(),
            $request->allFiles()
        );
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $result = $this->profileService->changePassword($request->user(), $request->validated());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }
}
