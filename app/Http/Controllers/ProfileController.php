<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UploadProfileDocsRequest;
use App\Models\HoSoXacThuc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function getProfile(Request $request)
    {
        $taiKhoan = $request->user();

        $khachHang = $taiKhoan->khachHang()->with('hoSoXacThuc')->first();

        if (! $khachHang) {
            return response()->json([
                'message' => 'Không tìm thấy thông tin khách hàng.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'taiKhoan' => [
                    'maTaiKhoan' => $taiKhoan->maTaiKhoan,
                    'tenDangNhap' => $taiKhoan->tenDangNhap,
                    'vaiTro' => $taiKhoan->vaiTro,
                    'trangThai' => $taiKhoan->trangThai,
                ],
                'khachHang' => $khachHang,
                'hoSoXacThuc' => $khachHang->hoSoXacThuc,
            ],
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $taiKhoan = $request->user();
        $khachHang = $taiKhoan->khachHang()->first();

        if (! $khachHang) {
            return response()->json([
                'message' => 'Không tìm thấy khách hàng.',
            ], 404);
        }

        $khachHang->update($request->validated());

        return response()->json([
            'message' => 'Cập nhật thông tin khách hàng thành công.',
            'data' => $khachHang->fresh(),
        ]);
    }

    public function uploadDocs(UploadProfileDocsRequest $request)
    {
        $taiKhoan = $request->user();
        $khachHang = $taiKhoan->khachHang()->first();

        if (! $khachHang) {
            return response()->json([
                'message' => 'Không tìm thấy khách hàng để upload hồ sơ.',
            ], 404);
        }

        $hoSo = $khachHang->hoSoXacThuc()->firstOrCreate([
            'maKhachHang' => $khachHang->maKhachHang,
        ]);

        $uploaded = [];

        $fieldMap = [
            'anhCCCDMatTruoc' => 'anhCCCDMatTruoc',
            'anhCCCDMatSau' => 'anhCCCDMatSau',
            'anhGPLX' => 'anhGPLX',
        ];

        foreach ($fieldMap as $inputName => $dbField) {
            if ($request->hasFile($inputName)) {
                $path = $request->file($inputName)->store('profiles/' . $khachHang->maKhachHang, 'public');
                $uploaded[$dbField] = Storage::url($path);
            }
        }

        $hoSo->fill($uploaded);
        $hoSo->trangThai = 'cho_duyet';
        $hoSo->ngayGui = now();
        $hoSo->save();

        return response()->json([
            'message' => 'Tải lên hồ sơ xác thực thành công.',
            'data' => $hoSo->fresh(),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $taiKhoan = $request->user();

        if (! Hash::check($request->matKhauCu, $taiKhoan->matKhau)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không chính xác.',
            ], 422);
        }

        $taiKhoan->matKhau = Hash::make($request->matKhauMoi);
        $taiKhoan->save();

        return response()->json([
            'message' => 'Đổi mật khẩu thành công.',
        ]);
    }
}
