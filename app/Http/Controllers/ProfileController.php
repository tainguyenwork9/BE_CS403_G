<?php

namespace App\Http\Controllers;

use App\Models\HoSoXacThuc;
use App\Models\KhachHang;
use App\Models\TaiKhoan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function getProfile(Request $request)
    {
        $taiKhoan = $request->user();
        $khachHang = KhachHang::where('maTaiKhoan', $taiKhoan->maTaiKhoan)->first();

        if (! $khachHang) {
            return response()->json([
                'status' => 0,
                'message' => 'Không tìm thấy thông tin khách hàng.',
            ], 404);
        }

        $hoSoXacThuc = HoSoXacThuc::where('maKhachHang', $khachHang->maKhachHang)
            ->orderBy('maHoSo', 'desc')
            ->first();

        return response()->json([
            'status' => 1,
            'data' => [
                'taiKhoan' => [
                    'maTaiKhoan' => $taiKhoan->maTaiKhoan,
                    'tenDangNhap' => $taiKhoan->tenDangNhap,
                    'vaiTro' => $taiKhoan->vaiTro,
                    'trangThai' => $taiKhoan->trangThai,
                ],
                'khachHang' => $khachHang,
                'hoSoXacThuc' => $hoSoXacThuc,
            ],
            'message' => 'Lấy thông tin hồ sơ cá nhân thành công.',
        ], 200);
    }

    public function updateProfile(Request $request)
    {
        $taiKhoan = $request->user();
        $khachHang = KhachHang::where('maTaiKhoan', $taiKhoan->maTaiKhoan)->first();

        if (! $khachHang) {
            return response()->json([
                'status' => 0,
                'message' => 'Không tìm thấy khách hàng.',
            ], 404);
        }

        KhachHang::where('maKhachHang', $khachHang->maKhachHang)->update([
            'hoTen' => $request->hoTen ?? $khachHang->hoTen,
            'ngaySinh' => $request->ngaySinh ?? $khachHang->ngaySinh,
            'soDienThoai' => $request->soDienThoai ?? $khachHang->soDienThoai,
            'email' => $request->email ?? $khachHang->email,
            'diaChi' => $request->diaChi ?? $khachHang->diaChi,
        ]);

        $khachHang = KhachHang::find($khachHang->maKhachHang);

        return response()->json([
            'status' => 1,
            'data' => $khachHang,
            'message' => 'Cập nhật thông tin khách hàng thành công.',
        ], 200);
    }

    public function uploadDocs(Request $request)
    {
        $taiKhoan = $request->user();
        $khachHang = KhachHang::where('maTaiKhoan', $taiKhoan->maTaiKhoan)->first();

        if (! $khachHang) {
            return response()->json([
                'status' => 0,
                'message' => 'Không tìm thấy khách hàng để upload hồ sơ.',
            ], 404);
        }

        $hoSo = HoSoXacThuc::firstOrCreate([
            'maKhachHang' => $khachHang->maKhachHang,
        ]);

        $payload = [
            'soCCCD' => $request->has('soCCCD') ? $request->soCCCD : $hoSo->soCCCD,
            'soGPLX' => $request->has('soGPLX') ? $request->soGPLX : $hoSo->soGPLX,
            'trangThai' => 'cho_duyet',
            'ngayGui' => now(),
        ];

        if ($request->hasFile('anhCCCDMatTruoc')) {
            $path = $request->file('anhCCCDMatTruoc')->store('profiles/' . $khachHang->maKhachHang, 'public');
            $payload['anhCCCDMatTruoc'] = Storage::url($path);
        }

        if ($request->hasFile('anhCCCDMatSau')) {
            $path = $request->file('anhCCCDMatSau')->store('profiles/' . $khachHang->maKhachHang, 'public');
            $payload['anhCCCDMatSau'] = Storage::url($path);
        }

        if ($request->hasFile('anhGPLX')) {
            $path = $request->file('anhGPLX')->store('profiles/' . $khachHang->maKhachHang, 'public');
            $payload['anhGPLX'] = Storage::url($path);
        }

        HoSoXacThuc::where('maHoSo', $hoSo->maHoSo)->update($payload);
        $hoSo = HoSoXacThuc::find($hoSo->maHoSo);

        return response()->json([
            'status' => 1,
            'data' => $hoSo,
            'message' => 'Tải lên hồ sơ xác thực thành công.',
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $taiKhoan = $request->user();

        if (! Hash::check((string) $request->matKhauCu, $taiKhoan->matKhau)) {
            return response()->json([
                'status' => 0,
                'message' => 'Mật khẩu hiện tại không chính xác.',
            ], 422);
        }

        TaiKhoan::where('maTaiKhoan', $taiKhoan->maTaiKhoan)->update([
            'matKhau' => Hash::make($request->matKhauMoi),
        ]);

        return response()->json([
            'status' => 1,
            'data' => null,
            'message' => 'Đổi mật khẩu thành công.',
        ], 200);
    }
}
