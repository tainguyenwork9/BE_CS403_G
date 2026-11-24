<?php

namespace App\Services;

use App\Models\HoSoXacThuc;
use Illuminate\Support\Facades\DB;

class AdminProfileService
{
    public function getProfiles(): array
    {
        $profiles = DB::table('ho_so_xac_thucs as h')
            ->join('khach_hangs as k', 'k.maKhachHang', '=', 'h.maKhachHang')
            ->select(
                'h.*',
                'k.hoTen',
                'k.soDienThoai',
                'k.email'
            )
            ->orderBy('h.maHoSo', 'desc')
            ->get();

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Lấy danh sách hồ sơ xác thực thành công.',
            'data' => $profiles,
        ];
    }

    public function getPendingProfiles(): array
    {
        $profiles = DB::table('ho_so_xac_thucs as h')
            ->join('khach_hangs as k', 'k.maKhachHang', '=', 'h.maKhachHang')
            ->where('h.trangThai', 'cho_duyet')
            ->select(
                'h.*',
                'k.hoTen',
                'k.soDienThoai',
                'k.email'
            )
            ->orderBy('h.maHoSo', 'desc')
            ->get();

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Lấy danh sách hồ sơ chờ duyệt thành công.',
            'data' => $profiles,
        ];
    }

    public function updateStatus(int $maHoSo, string $action): array
    {
        $profile = HoSoXacThuc::findOrFail($maHoSo);

        if ($action === 'duyet') {
            $profile->trangThai = 'da_duyet';
            $profile->ngayXacThuc = now();
        } else {
            $profile->trangThai = 'tu_choi';
            $profile->ngayXacThuc = null;
        }

        $profile->save();

        return [
            'status' => true,
            'code' => 200,
            'message' => $action === 'duyet'
                ? 'Hồ sơ đã được duyệt.'
                : 'Hồ sơ đã bị từ chối.',
            'data' => $profile,
        ];
    }
}

