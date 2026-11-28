<?php

namespace Database\Seeders;

use App\Models\HoSoXacThuc;
use App\Models\KhachHang;
use App\Models\TaiKhoan;
use App\Models\Xe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAccountsAndCustomers();
        $this->seedProfiles();
        $this->seedVehicles();
    }

    protected function seedAccountsAndCustomers(): void
    {
        $accounts = [
            [
                'tenDangNhap' => 'admin01',
                'matKhau' => 'Admin@123',
                'vaiTro' => 'admin',
                'trangThai' => true,
                'hoTen' => 'Nguyễn Văn Quản Trị',
                'ngaySinh' => '1990-05-14',
                'soDienThoai' => '0901000001',
                'email' => 'admin@motorent.vn',
                'diaChi' => '120 Lê Lợi, Quận 1, TP.HCM',
            ],
            [
                'tenDangNhap' => 'nhanvien01',
                'matKhau' => 'NhanVien@123',
                'vaiTro' => 'nhan_vien',
                'trangThai' => true,
                'hoTen' => 'Trần Thị Nhân Viên',
                'ngaySinh' => '1994-08-24',
                'soDienThoai' => '0901000002',
                'email' => 'nhanvien@motorent.vn',
                'diaChi' => '88 Nguyễn Huệ, Quận 1, TP.HCM',
            ],
            [
                'tenDangNhap' => 'khach01',
                'matKhau' => 'Khach@123',
                'vaiTro' => 'khach_hang',
                'trangThai' => true,
                'hoTen' => 'Phạm Minh Khách',
                'ngaySinh' => '1998-11-02',
                'soDienThoai' => '0902000001',
                'email' => 'khach1@example.com',
                'diaChi' => '15 Trần Hưng Đạo, Quận 5, TP.HCM',
            ],
            [
                'tenDangNhap' => 'khach02',
                'matKhau' => 'Khach@456',
                'vaiTro' => 'khach_hang',
                'trangThai' => true,
                'hoTen' => 'Lê Thị Hồng',
                'ngaySinh' => '2000-03-18',
                'soDienThoai' => '0902000002',
                'email' => 'khach2@example.com',
                'diaChi' => '45 Võ Văn Tần, Quận 3, TP.HCM',
            ],
        ];

        foreach ($accounts as $account) {
            $taiKhoan = TaiKhoan::query()->firstOrCreate(
                ['tenDangNhap' => $account['tenDangNhap']],
                [
                    'matKhau' => Hash::make($account['matKhau']),
                    'vaiTro' => $account['vaiTro'],
                    'trangThai' => $account['trangThai'],
                ]
            );

            KhachHang::query()->firstOrCreate(
                ['maTaiKhoan' => $taiKhoan->maTaiKhoan],
                [
                    'hoTen' => $account['hoTen'],
                    'ngaySinh' => $account['ngaySinh'],
                    'soDienThoai' => $account['soDienThoai'],
                    'email' => $account['email'],
                    'diaChi' => $account['diaChi'],
                ]
            );
        }

        $this->seedBulkCustomers();
    }

    protected function seedBulkCustomers(): void
    {
        for ($index = 43; $index <= 50; $index++) {
            $number = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $taiKhoan = TaiKhoan::query()->where('tenDangNhap', 'khach' . $number)->first();

            if ($taiKhoan) {
                $taiKhoan->delete();
            }
        }

        for ($index = 3; $index <= 10; $index++) {
            $number = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $taiKhoan = TaiKhoan::query()->firstOrCreate(
                ['tenDangNhap' => 'khach' . $number],
                [
                    'matKhau' => Hash::make('Khach@' . $number),
                    'vaiTro' => 'khach_hang',
                    'trangThai' => true,
                ]
            );

            KhachHang::query()->firstOrCreate(
                ['maTaiKhoan' => $taiKhoan->maTaiKhoan],
                [
                    'hoTen' => 'Khách hàng ' . $number,
                    'ngaySinh' => now()->subYears(20 + ($index % 25))->subDays($index)->toDateString(),
                    'soDienThoai' => '090' . str_pad((string) $index, 7, '0', STR_PAD_LEFT),
                    'email' => 'khach' . $number . '@example.com',
                    'diaChi' => 'Địa chỉ khách hàng ' . $number . ', TP.HCM',
                ]
            );
        }
    }

    protected function seedProfiles(): void
    {
        $profiles = [
            [
                'tenDangNhap' => 'khach01',
                'soCCCD' => '052198001234',
                'anhCCCDMatTruoc' => 'https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=800&q=80',
                'anhCCCDMatSau' => 'https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=800&q=80',
                'soGPLX' => 'B2-20240001',
                'anhGPLX' => 'https://images.unsplash.com/photo-1525609004556-c46c7d6cf023?auto=format&fit=crop&w=800&q=80',
                'trangThai' => 'da_duyet',
                'ngayGui' => now()->subDays(8),
                'ngayXacThuc' => now()->subDays(3),
            ],
            [
                'tenDangNhap' => 'khach02',
                'soCCCD' => '052198007891',
                'anhCCCDMatTruoc' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=800&q=80',
                'anhCCCDMatSau' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=800&q=80',
                'soGPLX' => 'B2-20240002',
                'anhGPLX' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?auto=format&fit=crop&w=800&q=80',
                'trangThai' => 'cho_duyet',
                'ngayGui' => now()->subDay(),
                'ngayXacThuc' => null,
            ],
        ];

        foreach ($profiles as $profile) {
            $taiKhoan = TaiKhoan::query()->where('tenDangNhap', $profile['tenDangNhap'])->firstOrFail();
            $khachHang = $taiKhoan->khachHang()->firstOrFail();

            HoSoXacThuc::query()->updateOrCreate(
                ['maKhachHang' => $khachHang->maKhachHang],
                [
                    'soCCCD' => $profile['soCCCD'],
                    'anhCCCDMatTruoc' => $profile['anhCCCDMatTruoc'],
                    'anhCCCDMatSau' => $profile['anhCCCDMatSau'],
                    'soGPLX' => $profile['soGPLX'],
                    'anhGPLX' => $profile['anhGPLX'],
                    'trangThai' => $profile['trangThai'],
                    'ngayGui' => $profile['ngayGui'],
                    'ngayXacThuc' => $profile['ngayXacThuc'],
                ]
            );
        }

        $this->seedBulkProfiles();
    }

    protected function seedBulkProfiles(): void
    {
        $defaultDocumentImage = 'https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=800&q=80';

        for ($index = 3; $index <= 10; $index++) {
            $number = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $taiKhoan = TaiKhoan::query()->where('tenDangNhap', 'khach' . $number)->firstOrFail();
            $khachHang = $taiKhoan->khachHang()->firstOrFail();
            $statusIndex = $index % 3;
            $status = match ($statusIndex) {
                0 => 'cho_duyet',
                1 => 'da_duyet',
                default => 'tu_choi',
            };

            HoSoXacThuc::query()->updateOrCreate(
                ['maKhachHang' => $khachHang->maKhachHang],
                [
                    'soCCCD' => '079' . str_pad((string) $index, 9, '0', STR_PAD_LEFT),
                    'anhCCCDMatTruoc' => $defaultDocumentImage,
                    'anhCCCDMatSau' => $defaultDocumentImage,
                    'soGPLX' => 'B2-2024' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'anhGPLX' => $defaultDocumentImage,
                    'trangThai' => $status,
                    'ngayGui' => now()->subDays($index % 45),
                    'ngayXacThuc' => $status === 'cho_duyet' ? null : now()->subDays($index % 30),
                ]
            );
        }
    }

    protected function seedVehicles(): void
    {
        $vehicles = [
            [
                'tenXe' => 'Honda Vision',
                'hangXe' => 'Honda',
                'bienSo' => '59-H1 123.45',
                'mauSac' => 'Trắng',
                'namSanXuat' => 2024,
                'giaThue' => 120000,
                'loaiXe' => 'Xe tay ga',
                'tinhTrang' => 'san_sang',
                'hinhAnh' => 'https://cdn.honda.com.vn/motorbike-versions/Image360/December2025/1765585834/0.png',
            ],
            [
                'tenXe' => 'Yamaha Exciter 155',
                'hangXe' => 'Yamaha',
                'bienSo' => '59-H2 456.78',
                'mauSac' => 'Xanh đen',
                'namSanXuat' => 2023,
                'giaThue' => 180000,
                'loaiXe' => 'Xe côn tay',
                'tinhTrang' => 'san_sang',
                'hinhAnh' => 'https://yamaha-motor.com.vn/wp/wp-content/uploads/2026/10/D199_Exciter-155_SMY-Den-Xanh-Bong_Goc-4-hires-768x645.png',
            ],
            [
                'tenXe' => 'Honda Winner X',
                'hangXe' => 'Honda',
                'bienSo' => '59-H3 789.01',
                'mauSac' => 'Đỏ đen',
                'namSanXuat' => 2022,
                'giaThue' => 170000,
                'loaiXe' => 'Xe côn tay',
                'tinhTrang' => 'san_sang',
                'hinhAnh' => 'https://cdn.honda.com.vn/motorbike-versions/Image360/September2025/1757787378/0.png',
            ],
            [
                'tenXe' => 'Yamaha Janus',
                'hangXe' => 'Yamaha',
                'bienSo' => '59-H4 234.56',
                'mauSac' => 'Xám',
                'namSanXuat' => 2021,
                'giaThue' => 100000,
                'loaiXe' => 'Xe tay ga',
                'tinhTrang' => 'san_sang',
                'hinhAnh' => 'https://yamaha-motor.com.vn/wp/wp-content/uploads/2025/12/Janus_Limited_Xam-Bong_G4-768x645.png',
            ],
            [
                'tenXe' => 'Honda Air Blade 160',
                'hangXe' => 'Honda',
                'bienSo' => '59-H5 567.89',
                'mauSac' => 'Đen nhám',
                'namSanXuat' => 2024,
                'giaThue' => 160000,
                'loaiXe' => 'Xe tay ga',
                'tinhTrang' => 'san_sang',
                'hinhAnh' => 'https://cdn.honda.com.vn/motorbike-versions/Image360/November2025/1762148885/0.png',
            ],
            [
                'tenXe' => 'Honda Cub 50',
                'hangXe' => 'Honda',
                'bienSo' => '59-H6 345.67',
                'mauSac' => 'Xanh ngọc',
                'namSanXuat' => 2021,
                'giaThue' => 90000,
                'loaiXe' => 'Xe số',
                'tinhTrang' => 'dang_thue',
                'hinhAnh' => 'https://cdn.honda.com.vn/motorbike-versions/July2024/4mvfY4O7TXBSDullbfAZ.png',
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Xe::query()->updateOrCreate(
                ['bienSo' => $vehicle['bienSo']],
                $vehicle
            );
        }
    }
}
