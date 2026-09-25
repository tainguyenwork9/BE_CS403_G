<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use App\Models\TaiKhoan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppApiFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_customer_can_register_and_update_profile(): void
    {
        $registerPayload = [
            'tenDangNhap' => 'khachmoi01',
            'matKhau' => 'Khach@123',
            'hoTen' => 'Nguyễn Văn Mới',
            'soDienThoai' => '0909999001',
            'email' => 'khachmoi01@example.com',
        ];

        $registerResponse = $this->postJson('/api/register', $registerPayload);
        $registerResponse->assertStatus(201)
            ->assertJsonPath('user.tenDangNhap', 'khachmoi01')
            ->assertJsonPath('user.khachHang.hoTen', 'Nguyễn Văn Mới');

        $token = $registerResponse->json('token');

        $profileResponse = $this->withToken($token)->getJson('/api/profile');
        $profileResponse->assertOk()
            ->assertJsonPath('data.khachHang.hoTen', 'Nguyễn Văn Mới');

        $updateResponse = $this->withToken($token)->putJson('/api/profile', [
            'hoTen' => 'Nguyễn Văn Mới Update',
            'email' => 'newmail@example.com',
            'diaChi' => '123 Lý Thái Tổ',
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.hoTen', 'Nguyễn Văn Mới Update');

        $changePasswordResponse = $this->withToken($token)->postJson('/api/change-password', [
            'matKhauCu' => 'Khach@123',
            'matKhauMoi' => 'Khach@456',
        ]);

        $changePasswordResponse->assertOk()
            ->assertJsonPath('message', 'Đổi mật khẩu thành công.');
    }

    public function test_vehicle_listing_uses_vehicle_codes_and_detail_endpoint(): void
    {
        $listingResponse = $this->getJson('/api/vehicles');
        $listingResponse->assertOk();
        $data = $listingResponse->json('data');

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('maXe', $data[0]);
        $this->assertMatchesRegularExpression('/^MX\d{3}$/', (string) $data[0]['maXe']);

        $detailResponse = $this->getJson('/api/vehicles/' . $data[0]['maXe']);
        $detailResponse->assertOk()
            ->assertJsonPath('data.maXe', $data[0]['maXe']);
    }

    public function test_staff_can_review_customer_profile(): void
    {
        $staff = $this->postJson('/api/login', [
            'tenDangNhap' => 'nhanvien01',
            'matKhau' => 'NhanVien@123',
        ]);

        $staff->assertOk();
        $token = $staff->json('token');

        $pendingResponse = $this->withToken($token)->getJson('/api/admin/profiles/pending');
        $pendingResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'maHoSo',
                        'hoTen',
                        'soCCCD',
                        'soGPLX',
                        'trangThai',
                    ],
                ],
            ]);

        $profileId = $pendingResponse->json('data.0.maHoSo');

        $updateStatusResponse = $this->withToken($token)->postJson('/api/admin/profiles/update-status', [
            'maHoSo' => $profileId,
            'action' => 'duyet',
        ]);

        $updateStatusResponse->assertOk()
            ->assertJsonPath('data.trangThai', 'da_duyet');

        $allProfilesResponse = $this->withToken($token)->getJson('/api/admin/profiles');
        $allProfilesResponse->assertOk();
        $updatedProfile = collect($allProfilesResponse->json('data'))
            ->firstWhere('maHoSo', $profileId);

        $this->assertSame('da_duyet', $updatedProfile['trangThai']);
    }

    public function test_only_admin_can_view_statistics(): void
    {
        $admin = $this->postJson('/api/login', [
            'tenDangNhap' => 'admin01',
            'matKhau' => 'Admin@123',
        ]);

        $admin->assertOk();

        $this->withToken($admin->json('token'))
            ->getJson('/api/admin/statistics')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'accounts' => ['total', 'customers', 'staff', 'admins'],
                    'profiles' => ['total', 'pending', 'approved', 'rejected'],
                    'vehicles' => ['total', 'available', 'rented'],
                ],
            ]);

        $staff = TaiKhoan::where('tenDangNhap', 'nhanvien01')->firstOrFail();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/admin/statistics')
            ->assertForbidden();
    }
}
