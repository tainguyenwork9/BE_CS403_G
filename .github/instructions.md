# Quy tắc code Backend (BE_CS403_G)

## 1. Công nghệ & Kiến trúc bắt buộc
- **Stack:** Laravel 12, PHP 8.2+, Laravel Sanctum, Database SQLite (`database/database.sqlite`)[cite: 4, 8].
- **Kiến trúc:** Bắt buộc tách lớp `Controller -> FormRequest -> Service -> Model`[cite: 4]. Controller chỉ nhận request, gọi Service và trả JSON; toàn bộ logic nghiệp vụ và transaction đặt trong `app/Services/`[cite: 4].
- **Format JSON Response chuẩn:** Mọi API phải trả về đúng cấu trúc:
  `{ "status": bool, "message": string, "data": mixed }` kèm HTTP status code chuẩn (`200`, `201`, `400`, `403`, `404`, `409`, `422`).
- **Tài liệu API:** Luôn đọc và cập nhật `BE_CS403_G/docs/api-contract.md` và `BE_CS403_G/docs/schema.dbml` khi thêm hoặc sửa endpoint[cite: 8].

## 2. Quy ước Database & Models (TUYỆT ĐỐI GIỮ NGUYÊN TÊN BẢNG/CỘT)
- `TaiKhoan` (bảng `tai_khoans`, PK: `maTaiKhoan`): `tenDangNhap`, `matKhau`, `vaiTro` (`khach_hang`, `nhan_vien`, `admin`), `trangThai` (boolean).
- `KhachHang` (bảng `khach_hangs`, PK: `maKhachHang`, FK: `maTaiKhoan`): `hoTen`, `ngaySinh`, `soDienThoai`, `email`, `diaChi`.
- `HoSoXacThuc` (bảng `ho_so_xac_thucs`, PK: `maHoSo`, FK: `maKhachHang`): `soCCCD`, `anhCCCDMatTruoc`, `anhCCCDMatSau`, `soGPLX`, `anhGPLX`, `trangThai`, `ngayGui`, `ngayXacThuc`.
- `Xe` (bảng `xes`, PK: `maXe`): `tenXe`, `hangXe`, `bienSo`, `mauSac`, `namSanXuat`, `giaThue` (giá thuê/ngày - VNĐ), `loaiXe`, `tinhTrang` (`san_sang`, `dang_thue`, `bao_tri`), `hinhAnh`.
- `Booking` (bảng `bookings`, PK: `id`, FK: `user_id` -> `tai_khoans.maTaiKhoan`, `car_id` -> `xes.maXe`): `start_date`, `end_date`, `options` (JSON), `total_amount`, `status` (`PENDING_PAYMENT`, `PAID`, `CONFIRMED`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`, `EXPIRED`, `PAYMENT_MISMATCH`), `transfer_code`, `expires_at`[cite: 5].
- `PaymentTransaction` (bảng `payment_transactions`, PK: `id`, FK: `booking_id`): `amount`, `bank_trans_id`, `trans_description`, `status`, `raw_payload`[cite: 5].

## 3. Quy tắc nghiệp vụ & SQLite
- **Tính tiền:** Giá `giaThue` tính theo ngày (24h). Số ngày thuê = `ceil(tổng_giờ / 24)`, tối thiểu 1 ngày.
- **Concurrency:** Dùng `DB::transaction()` kết hợp `lockForUpdate()` khi tạo đơn đặt xe hoặc xử lý webhook thanh toán.
- **SQLite:** Nếu tạo migration mới, viết cú pháp tương thích SQLite (tránh `ALTER TABLE ... MODIFY COLUMN` đặc thù của MySQL) và chạy `php artisan migrate` sau khi tạo[cite: 8].