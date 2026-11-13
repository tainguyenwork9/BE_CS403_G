<?php

namespace App\Services;

use App\Models\KhachHang;
use App\Models\TaiKhoan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $taiKhoan = TaiKhoan::create([
                'tenDangNhap' => $data['tenDangNhap'],
                'matKhau' => Hash::make($data['matKhau']),
                'vaiTro' => 'khach_hang',
                'trangThai' => true,
            ]);

            $khachHang = KhachHang::create([
                'maTaiKhoan' => $taiKhoan->maTaiKhoan,
                'hoTen' => $data['hoTen'],
                'soDienThoai' => $data['soDienThoai'],
                'email' => $data['email'],
            ]);

            $token = $taiKhoan->createToken('auth_token')->plainTextToken;

            return [
                'status' => true,
                'code' => 201,
                'message' => 'Đăng ký tài khoản thành công.',
                'data' => [
                    'user' => [
                        'maTaiKhoan' => $taiKhoan->maTaiKhoan,
                        'tenDangNhap' => $taiKhoan->tenDangNhap,
                        'vaiTro' => $taiKhoan->vaiTro,
                        'trangThai' => $taiKhoan->trangThai,
                        'khachHang' => $khachHang,
                    ],
                    'token' => $token,
                ],
                'user' => [
                    'maTaiKhoan' => $taiKhoan->maTaiKhoan,
                    'tenDangNhap' => $taiKhoan->tenDangNhap,
                    'vaiTro' => $taiKhoan->vaiTro,
                    'trangThai' => $taiKhoan->trangThai,
                    'khachHang' => $khachHang,
                ],
                'token' => $token,
            ];
        });
    }

    public function login(array $credentials): array
    {
        $taiKhoan = TaiKhoan::where('tenDangNhap', $credentials['tenDangNhap'])->first();

        if (! $taiKhoan || ! Hash::check($credentials['matKhau'], $taiKhoan->matKhau)) {
            return [
                'status' => false,
                'code' => 401,
                'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.',
                'data' => null,
            ];
        }

        if (! $taiKhoan->trangThai) {
            return [
                'status' => false,
                'code' => 403,
                'message' => 'Tài khoản của bạn đã bị khóa.',
                'data' => null,
            ];
        }

        $token = $taiKhoan->createToken('auth_token')->plainTextToken;
        $userPayload = [
            'maTaiKhoan' => $taiKhoan->maTaiKhoan,
            'tenDangNhap' => $taiKhoan->tenDangNhap,
            'vaiTro' => $taiKhoan->vaiTro,
            'trangThai' => $taiKhoan->trangThai,
            'khachHang' => $taiKhoan->khachHang,
        ];

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Đăng nhập thành công.',
            'data' => [
                'user' => $userPayload,
                'token' => $token,
            ],
            'user' => $userPayload,
            'token' => $token,
        ];
    }

    public function logout(?TaiKhoan $taiKhoan): array
    {
        $token = $taiKhoan?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Đăng xuất thành công.',
            'data' => null,
        ];
    }
}

