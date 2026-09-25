<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\KhachHang;
use App\Models\TaiKhoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

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

        return response()->json([
            'message' => 'Đăng ký tài khoản thành công.',
            'user' => [
                'maTaiKhoan' => $taiKhoan->maTaiKhoan,
                'tenDangNhap' => $taiKhoan->tenDangNhap,
                'vaiTro' => $taiKhoan->vaiTro,
                'trangThai' => $taiKhoan->trangThai,
                'khachHang' => $khachHang,
            ],
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $taiKhoan = TaiKhoan::where('tenDangNhap', $credentials['tenDangNhap'])->first();

        if (! $taiKhoan || ! Hash::check($credentials['matKhau'], $taiKhoan->matKhau)) {
            return response()->json([
                'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.',
            ], 401);
        }

        $token = $taiKhoan->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công.',
            'user' => [
                'maTaiKhoan' => $taiKhoan->maTaiKhoan,
                'tenDangNhap' => $taiKhoan->tenDangNhap,
                'vaiTro' => $taiKhoan->vaiTro,
                'trangThai' => $taiKhoan->trangThai,
                'khachHang' => $taiKhoan->khachHang,
            ],
            'token' => $token,
        ]);
    }
}
