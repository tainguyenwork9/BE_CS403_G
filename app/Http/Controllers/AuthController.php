<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function register(RegisterRequest $request)
    {
        $result = $this->authService->register($request->validated());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->authService->login($request->validated());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function logout(Request $request)
    {
        $result = $this->authService->logout($request->user());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }
}
