<?php

namespace App\Services;

use App\Models\TaiKhoan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    public function getProfile(TaiKhoan $taiKhoan): array
    {
        $khachHang = $taiKhoan->khachHang()->with('hoSoXacThuc')->first();

        if (! $khachHang) {
            return [
                'status' => false,
                'code' => 404,
                'message' => 'Không tìm thấy thông tin khách hàng.',
                'data' => null,
            ];
        }

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Lấy thông tin hồ sơ cá nhân thành công.',
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
        ];
    }

    public function updateProfile(TaiKhoan $taiKhoan, array $data): array
    {
        $khachHang = $taiKhoan->khachHang()->first();

        if (! $khachHang) {
            return [
                'status' => false,
                'code' => 404,
                'message' => 'Không tìm thấy khách hàng.',
                'data' => null,
            ];
        }

        $khachHang->update($data);

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Cập nhật thông tin khách hàng thành công.',
            'data' => $khachHang->fresh(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files
     */
    public function uploadDocs(TaiKhoan $taiKhoan, array $data, array $files): array
    {
        $khachHang = $taiKhoan->khachHang()->first();

        if (! $khachHang) {
            return [
                'status' => false,
                'code' => 404,
                'message' => 'Không tìm thấy khách hàng để upload hồ sơ.',
                'data' => null,
            ];
        }

        $hoSo = $khachHang->hoSoXacThuc()->firstOrCreate([
            'maKhachHang' => $khachHang->maKhachHang,
        ]);

        $payload = [];

        if (array_key_exists('soCCCD', $data)) {
            $payload['soCCCD'] = $data['soCCCD'];
        }

        if (array_key_exists('soGPLX', $data)) {
            $payload['soGPLX'] = $data['soGPLX'];
        }

        $fieldMap = [
            'anhCCCDMatTruoc' => 'anhCCCDMatTruoc',
            'anhCCCDMatSau' => 'anhCCCDMatSau',
            'anhGPLX' => 'anhGPLX',
        ];

        foreach ($fieldMap as $inputName => $dbField) {
            if (! empty($files[$inputName]) && $files[$inputName] instanceof UploadedFile) {
                $path = $files[$inputName]->store('profiles/' . $khachHang->maKhachHang, 'public');
                $payload[$dbField] = Storage::url($path);
            }
        }

        $hoSo->fill($payload);
        $hoSo->trangThai = 'cho_duyet';
        $hoSo->ngayGui = now();
        $hoSo->save();

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Tải lên hồ sơ xác thực thành công.',
            'data' => $hoSo->fresh(),
        ];
    }

    public function changePassword(TaiKhoan $taiKhoan, array $data): array
    {
        if (! Hash::check($data['matKhauCu'], $taiKhoan->matKhau)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => 'Mật khẩu hiện tại không chính xác.',
                'data' => null,
            ];
        }

        $taiKhoan->matKhau = Hash::make($data['matKhauMoi']);
        $taiKhoan->save();

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Đổi mật khẩu thành công.',
            'data' => null,
        ];
    }
}

