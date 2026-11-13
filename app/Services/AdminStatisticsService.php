<?php

namespace App\Services;

use App\Models\HoSoXacThuc;
use App\Models\TaiKhoan;
use App\Models\Xe;

class AdminStatisticsService
{
    public function getStatistics(): array
    {
        return [
            'status' => true,
            'code' => 200,
            'message' => 'Lấy thống kê hệ thống thành công.',
            'data' => [
                'accounts' => [
                    'total' => TaiKhoan::count(),
                    'customers' => TaiKhoan::where('vaiTro', 'khach_hang')->count(),
                    'staff' => TaiKhoan::where('vaiTro', 'nhan_vien')->count(),
                    'admins' => TaiKhoan::where('vaiTro', 'admin')->count(),
                ],
                'profiles' => [
                    'total' => HoSoXacThuc::count(),
                    'pending' => HoSoXacThuc::where('trangThai', 'cho_duyet')->count(),
                    'approved' => HoSoXacThuc::where('trangThai', 'da_duyet')->count(),
                    'rejected' => HoSoXacThuc::where('trangThai', 'tu_choi')->count(),
                ],
                'vehicles' => [
                    'total' => Xe::count(),
                    'available' => Xe::where('tinhTrang', 'san_sang')->count(),
                    'rented' => Xe::where('tinhTrang', 'dang_thue')->count(),
                ],
            ],
        ];
    }
}

