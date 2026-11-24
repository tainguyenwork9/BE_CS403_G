<?php

namespace App\Http\Controllers;

use App\Models\KhachHang;
use App\Models\TaiKhoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        if (TaiKhoan::where('tenDangNhap', $request->tenDangNhap)->exists()) {
            return response()->json([
                'status' => 0,
                'message' => 'Tên đăng nhập đã tồn tại.',
            ], 422);
        }

        $result = DB::transaction(function () use ($request) {
            $taiKhoan = TaiKhoan::create([
                'tenDangNhap' => $request->tenDangNhap,
                'matKhau' => Hash::make($request->matKhau),
                'vaiTro' => 'khach_hang',
                'trangThai' => true,
            ]);

            $khachHang = KhachHang::create([
                'maTaiKhoan' => $taiKhoan->maTaiKhoan,
                'hoTen' => $request->hoTen,
                'soDienThoai' => $request->soDienThoai,
                'email' => $request->email,
            ]);

            $token = $taiKhoan->createToken('auth_token')->plainTextToken;

            $userPayload = [
                'maTaiKhoan' => $taiKhoan->maTaiKhoan,
                'tenDangNhap' => $taiKhoan->tenDangNhap,
                'vaiTro' => $taiKhoan->vaiTro,
                'trangThai' => $taiKhoan->trangThai,
                'khachHang' => $khachHang,
            ];

            return [
                'user' => $userPayload,
                'token' => $token,
            ];
        });

        return response()->json([
            'status' => 1,
            'data' => $result,
            'user' => $result['user'],
            'token' => $result['token'],
            'message' => 'Đăng ký tài khoản thành công.',
        ], 200);
    }

    public function login(Request $request)
    {
        $taiKhoan = TaiKhoan::where('tenDangNhap', $request->tenDangNhap)->first();

        if (! $taiKhoan || ! Hash::check((string) $request->matKhau, $taiKhoan->matKhau)) {
            return response()->json([
                'status' => 0,
                'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.',
            ], 401);
        }

        if (! $taiKhoan->trangThai) {
            return response()->json([
                'status' => 0,
                'message' => 'Tài khoản của bạn đã bị khóa.',
            ], 403);
        }

        $khachHang = KhachHang::where('maTaiKhoan', $taiKhoan->maTaiKhoan)->first();
        $token = $taiKhoan->createToken('auth_token')->plainTextToken;

        $userPayload = [
            'maTaiKhoan' => $taiKhoan->maTaiKhoan,
            'tenDangNhap' => $taiKhoan->tenDangNhap,
            'vaiTro' => $taiKhoan->vaiTro,
            'trangThai' => $taiKhoan->trangThai,
            'khachHang' => $khachHang,
        ];

        return response()->json([
            'status' => 1,
            'data' => [
                'user' => $userPayload,
                'token' => $token,
            ],
            'user' => $userPayload,
            'token' => $token,
            'message' => 'Đăng nhập thành công.',
        ], 200);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'status' => 1,
            'message' => 'Đăng xuất thành công.',
        ], 200);
    }
}
