# HƯỚNG DẪN CHẠY DEMO & KỊCH BẢN THUYẾT TRÌNH — DỰ ÁN THUÊ XE MÁY MOTORENT (CS403_G)

Tài liệu này bám sát **100% mã nguồn thực tế hiện tại** của `BE_CS403_G` (Laravel 12 + Sanctum + SQLite) và `FE_CS403_G` (Vue 3 Options API + Bootstrap 5 + Vite).

---

## 1. Cách khởi chạy dự án trước giờ thuyết trình

### 1.1. Khởi chạy Backend (`BE_CS403_G`)
Mở Terminal thứ nhất tại thư mục `BE_CS403_G`:

```bash
cd BE_CS403_G

# (Chỉ chạy nếu lần đầu kéo code về máy mới)
composer install
cp .env.example .env
php artisan key:generate

# Chạy migration và nạp dữ liệu mẫu (Tài khoản, Hồ sơ KYC, Đội xe)
php artisan migrate --seed

# Tạo liên kết thư mục public/storage để hiển thị ảnh CCCD/GPLX khi khách tự upload mới
php artisan storage:link

# BẮT BUỘC chạy đúng host 127.0.0.1 và port 8000
php artisan serve --host=127.0.0.1 --port=8000
```
- **API Base URL:** `http://127.0.0.1:8000/api`

### 1.2. Khởi chạy Frontend (`FE_CS403_G`)
Mở Terminal thứ hai tại thư mục `FE_CS403_G`:

```bash
cd FE_CS403_G

# (Chỉ chạy nếu chưa có node_modules)
npm install

# Chạy Vite Dev Server
npm run dev
```
- **Link truy cập giao diện Web:** `http://localhost:5173`

### 1.3. Lưu ý môi trường quan trọng để KHÔNG bị lỗi khi lên thuyết trình
1. **Đúng cổng Backend `127.0.0.1:8000`:** Toàn bộ các trang Vue gọi trực tiếp tới `http://127.0.0.1:8000/api/...`, vì vậy không đổi sang cổng khác (như `8001` hay `localhost` của Apache/XAMPP).
2. **Kết nối Internet:** Máy thuyết trình cần có mạng Internet (Wi-Fi / 4G) để trình duyệt tải ảnh mã QR động trực tiếp từ `https://img.vietqr.io/...` và ảnh xe minh họa từ Unsplash.
3. **Cơ chế tự làm sạch phiên đăng nhập:** Mỗi lần bật lại `npm run dev` hoặc mở tab mới, hệ thống tự động đăng xuất các phiên cũ (nếu không tích *"Ghi nhớ đăng nhập"*) để trang chủ luôn bắt đầu sạch sẽ ở trạng thái Khách vãng lai.
4. **Mẹo chuẩn bị 2 cửa sổ trình duyệt:** Mở sẵn **1 cửa sổ thường** (dành cho Khách hàng) và **1 cửa sổ Ẩn danh / Incognito** (đăng nhập sẵn Nhân viên vận hành `nhanvien01`) để chuyển qua lại mượt mà trong lúc thuyết trình mà không tốn thời gian đăng xuất/đăng nhập nhiều lần.
5. **Reset sạch dữ liệu trước buổi chấm (nếu đã test thử nhiều đơn rác):**
   ```bash
   cd BE_CS403_G
   php artisan migrate:fresh --seed
   ```

---

## 2. Danh sách Tài khoản & Đội xe Demo (Trích xuất từ `DatabaseSeeder.php`)

### 2.1. Danh sách tài khoản theo từng vai trò

| Vai trò & Tình trạng KYC | Tên đăng nhập (`tenDangNhap`) | Mật khẩu (`matKhau`) | Họ tên | Mục đích sử dụng khi Demo |
|---|---|---|---|---|
| **Khách hàng (Đã duyệt KYC)** | `khach01` | `Khach@123` | Phạm Minh Khách | **Tài khoản chính** để demo Đặt xe, Thanh toán QR, Hủy đơn, Lịch sử & Thuê lại ngay lập tức. |
| **Khách hàng (Đã duyệt KYC - Dự phòng)** | `khach004` | `Khach@004` | Khách hàng 004 | Dùng để test đặt trùng lịch xe hoặc dự phòng cho `khach01`. |
| **Khách hàng (Chờ duyệt KYC)** | `khach02` | `Khach@456` | Lê Thị Hồng | Demo bị chặn `403` khi cố đặt xe lúc chưa duyệt KYC -> sau đó qua Nhân viên bấm duyệt -> đặt xe thành công. |
| **Khách hàng (Bị từ chối KYC)** | `khach005` | `Khach@005` | Khách hàng 005 | Demo trạng thái hồ sơ bị từ chối cần cập nhật/gửi lại giấy tờ. |
| **Nhân viên vận hành** | `nhanvien01` | `NhanVien@123` | Trần Thị Nhân Viên | Duyệt hồ sơ KYC (`/kiem-tra-ho-so`) & Quản lý toàn bộ đơn đặt xe (`/quan-ly-don-hang`). |
| **Quản trị viên (Admin)** | `admin01` | `Admin@123` | Nguyễn Văn Quản Trị | Toàn quyền Nhân viên + Xem Dashboard Thống kê hệ thống (`/thong-ke`). |

### 2.2. Danh sách đội xe mẫu trong Database

| Mã xe hiển thị | Tên xe (`tenXe`) | Hãng / Loại | Biển số (`bienSo`) | Giá thuê / ngày | Trạng thái ban đầu |
|---|---|---|---|---|---|
| `MX001` | Honda Vision | Honda · Xe tay ga | `59-H1 123.45` | `120.000đ` | `san_sang` (Sẵn sàng) |
| `MX002` | Yamaha Exciter 155 | Yamaha · Xe côn tay | `59-H2 456.78` | `180.000đ` | `san_sang` (Sẵn sàng) |
| `MX003` | Honda Winner X | Honda · Xe côn tay | `59-H3 789.01` | `170.000đ` | `san_sang` (Sẵn sàng) |
| `MX004` | Yamaha Janus | Yamaha · Xe tay ga | `59-H4 234.56` | `100.000đ` | `san_sang` (Sẵn sàng) |
| `MX005` | Honda Air Blade 160 | Honda · Xe tay ga | `59-H5 567.89` | `160.000đ` | `san_sang` (Sẵn sàng) |
| `MX006` | Honda Cub 50 | Honda · Xe số | `59-H6 345.67` | `90.000đ` | `dang_thue` (Đang bận) |

---

## 3. Kịch bản Demo từng bước (Walkthrough 7–10 phút)

### Bước 1: Luồng Khách vãng lai & Ràng buộc KYC trước khi đặt xe (1.5 phút)
1. Mở `http://localhost:5173`: giới thiệu nhanh Trang chủ, Navbar chưa đăng nhập và danh sách xe sẵn sàng.
2. Vào menu **Xe thuê** (`/tim-kiem-xe`): thử bộ lọc theo **Loại xe** (*Xe tay ga*), **Hãng xe** (*Honda*), kéo thanh trượt **Giá thuê tối đa** và sắp xếp giá tăng/giảm.
3. Bấm **Đăng nhập** (`/dang-nhap`), đăng nhập bằng tài khoản **chưa duyệt KYC**: `khach02` / `Khach@456`.
   - Mở trang **Hồ sơ** (`/ho-so-ca-nhan`) chỉ cho giảng viên thấy badge trạng thái đang là **`Chờ duyệt`** kèm thông tin CCCD/GPLX (có thể sửa thử địa chỉ hoặc chọn ảnh mới và bấm **Lưu hồ sơ**).
4. Quay lại chọn xe `Honda Vision (MX001)` -> bấm **Tạo yêu cầu thuê xe**:
   - Hệ thống lập tức chặn với mã `403` và hiển thị khung cảnh báo màu vàng **"Yêu cầu xác thực CCCD / GPLX"** kèm nút dẫn thẳng về trang Hồ sơ cá nhân (chứng minh ràng buộc KYC của **PB08**).

---

### Bước 2: Luồng Nhân viên vận hành duyệt hồ sơ KYC (1 phút)
1. Chuyển sang cửa sổ trình duyệt thứ 2 (Ẩn danh), đăng nhập tài khoản Nhân viên: `nhanvien01` / `NhanVien@123`.
2. Trên Navbar của Nhân viên, vào mục **Duyệt hồ sơ** (`/kiem-tra-ho-so`):
   - Giới thiệu 3 thẻ thống kê (*Tổng hồ sơ*, *Chờ duyệt*, *Đã xử lý*), lọc tab **Chờ duyệt** để thấy hồ sơ của **Lê Thị Hồng (`khach02`)**.
   - Bấm nút **Xem chi tiết (biểu tượng con mắt)** để mở Modal kiểm tra ảnh CCCD mặt trước/sau và GPLX -> bấm nút **Xác thực tài khoản** (`Đã duyệt`).

---

### Bước 3: Luồng lõi Sprint 2 — Đặt xe, Thanh toán VietQR tự động & Hủy đơn (3.5 phút)
1. **Đặt xe thành công (`PB08`):**
   - Quay lại cửa sổ của khách hàng (`khach02` vừa được duyệt, hoặc dùng `khach01` / `Khach@123`).
   - Tại trang chi tiết xe `Honda Vision (MX001 - 120.000đ/ngày)`:
     - Chọn **Thời gian nhận xe** (lùi ra sau tối thiểu 2 ngày so với hiện tại để lát nữa demo chính sách hoàn tiền khi hủy) và **Thời gian trả xe** (ví dụ 2 ngày).
     - Tích chọn thêm **Gói bảo hiểm chuyến đi toàn diện (+30.000đ)**.
     - Chỉ rõ bảng tính tiền tự động làm tròn theo 24 giờ: `2 ngày x 120.000đ + 30.000đ = 270.000đ`.
     - Bấm **Tạo yêu cầu thuê xe** -> chuyển sang màn hình Thanh toán QR (`/thanh-toan/{id}`).
2. **Thanh toán VietQR & Giả lập Webhook (`PB09`):**
   - Giới thiệu trên màn hình:
     - Ảnh **VietQR động** (Ngân hàng Vietcombank, STK `9813002890`, tự nhúng sẵn đúng số tiền `270.000đ` và nội dung bắt buộc `THUEXE_{id}`).
     - Nút **Copy** nhanh STK / Số tiền / Nội dung chuyển khoản.
     - **Đồng hồ đếm ngược 15 phút** giữ xe và cơ chế **Short Polling tự động kiểm tra trạng thái đơn mỗi 3 giây**.
   - *(Tuỳ chọn gây ấn tượng)*: Bấm nút **"Giả lập Webhook chuyển thiếu tiền (PAYMENT_MISMATCH)"** ở cột phải -> cảnh báo vàng xuất hiện ngay lập tức báo chuyển thiếu tiền.
   - Bấm nút xanh **"Giả lập thanh toán thành công (Test Webhook)"** ngay dưới mã QR -> hệ thống xử lý webhook tự động, chuyển đơn sang **`PAID`**, khóa xe sang `dang_thue` và tự động nhảy sang giao diện **Đặt xe thành công & Hợp đồng điện tử `#HD-{id}`**.
3. **Quản lý đơn cá nhân & Chính sách Hủy đơn (`PB10`):**
   - Bấm nút **Quản lý đơn đặt xe của tôi** (`/don-dat-xe`):
     - Giới thiệu 2 Tab tách biệt: **Quản lý đơn đặt xe** (chỉ chứa đơn đang hoạt động) và **Lịch sử thuê xe** (đơn đã kết thúc), cùng 4 thẻ thống kê khớp số lượng.
   - Bấm nút **Hủy đơn** trên đơn vừa thanh toán -> Modal xác nhận hiện rõ **Chính sách hoàn tiền thực tế** (100% trong 1h đầu hoặc trước ≥7 ngày; 70% trước ≥24h; <24h liên hệ hotline) -> nhập lý do *"Thay đổi lịch công tác"* -> bấm **Xác nhận hủy đơn**.
   - Hệ thống thông báo hủy thành công kèm **số tiền hoàn dự kiến (`100%`)**, tự động mở lại xe về `san_sang`, và chuyển đơn sang tab **Lịch sử thuê xe**.

---

### Bước 4: Luồng Nhân viên Quản lý đơn vận hành (`PB11`) & Lịch sử / Thuê lại (`PB12`, `PB13`) (2.5 phút)
1. **Tạo nhanh 1 đơn thứ 2** trên xe `Yamaha Exciter 155 (MX002)` và bấm **Giả lập thanh toán thành công** để đơn ở trạng thái **`PAID`** (xe `MX002` lúc này chuyển sang `dang_thue`).
2. **Nhân viên xử lý vòng đời đơn (`PB11`):**
   - Chuyển sang cửa sổ Nhân viên (`nhanvien01`) -> vào mục **Quản lý đơn** (`/quan-ly-don-hang`).
   - Giới thiệu 6 thẻ thống kê toàn hệ thống, thử ô tìm kiếm theo mã `THUEXE_...` hoặc tên khách / biển số xe.
   - Trên dòng đơn `PAID` của `MX002`:
     - Bấm **Xác nhận** -> đơn chuyển sang `CONFIRMED`.
     - Bấm **Bàn giao** -> đơn chuyển sang `IN_PROGRESS` (khách đang nhận xe đi).
3. **Demo Thuê lại — Case Xe đang bận (`PB12` ngoại lệ):**
   - *(Mẹo hay)*: Nếu có đơn cũ đã hủy/hoàn thành của một xe hiện đang `dang_thue` (hoặc tạo 1 đơn nháp giữ xe trước rồi ở tab Lịch sử bấm **Thuê lại** đúng xe đó): hệ thống gọi `GET /api/bookings/{id}/rebook-info`, phát hiện `tinhTrang !== 'san_sang'` nên giữ nguyên trang và bật thông báo vàng: *"Xe [Tên xe - Biển số] hiện không sẵn sàng để thuê lại. Vui lòng chọn xe khác hoặc thử lại sau."*
4. **Hoàn tất chuyến thuê & Thuê lại thành công (`PB11` -> `PB13` -> `PB12`):**
   - Bên cửa sổ Nhân viên: bấm nút **Hoàn tất** trên đơn `IN_PROGRESS` -> đơn chuyển sang **`COMPLETED`** và hệ thống tự động giải phóng xe `MX002` về **`san_sang`**.
   - Quay lại cửa sổ Khách hàng -> mở tab **Lịch sử thuê xe** (`PB13`): lọc thử các nút *Tất cả / Hoàn thành / Đã hủy / Hết hạn*, bấm **Xem chi tiết** (nút Thanh toán đã được ẩn cho đơn lịch sử).
   - Bấm nút **Thuê lại** trên đơn `COMPLETED` vừa xong -> vì `MX002` đã về `san_sang`, hệ thống điều hướng thẳng sang trang Đặt xe `/chi-tiet-xe/MX002?rebookFrom={id}`, hiển thị banner thông báo **Thuê lại từ đơn cũ** và **tự động tích sẵn các tuỳ chọn đi kèm (`options`)** như đơn trước, khách chỉ cần chọn ngày mới!
5. *(Kết thúc - 30 giây)*: Đăng nhập `admin01` / `Admin@123`, mở trang **Thống kê** (`/thong-ke`) trình bày tổng quan số liệu hệ thống.

---

## 4. Điểm kỹ thuật nổi bật nên nhấn mạnh với Giảng viên

1. **Chống đặt trùng xe (Concurrency Control) bằng Pessimistic Locking:**
   - Trong `BookingController@store`, toàn bộ bước kiểm tra xe và tạo đơn được bọc trong `DB::transaction()` kết hợp `Xe::where('maXe', ...)->lockForUpdate()` và kiểm tra giao thoa khoảng thời gian (`start_date < requested_end` VÀ `end_date > requested_start`) trên các trạng thái giữ xe (`PENDING_PAYMENT`, `PAID`, `CONFIRMED`, `IN_PROGRESS`). Hai khách bấm đặt cùng một giây sẽ không bao giờ bị trùng đơn.
2. **Xác nhận thanh toán tự động & Chống trùng lặp Webhook (Idempotency):**
   - `PaymentController@webhook` lưu vết mọi giao dịch vào bảng `payment_transactions` với khóa duy nhất `bank_trans_id`. Nếu cổng thanh toán gửi lặp lại cùng 1 webhook nhiều lần, hệ thống nhận diện ngay và trả kết quả Idempotent thay vì xử lý trùng.
   - Bắt đủ các biên ngoại lệ thực tế: chuyển sai nội dung (`UNMATCHED`), đơn đã hết hạn (`EXPIRED_BOOKING`), chuyển thiếu tiền (`PAYMENT_MISMATCH` kèm ghi log cảnh báo Admin).
3. **Đồng bộ tự động trạng thái Đội xe (`xes.tinhTrang`):**
   - Khi thanh toán thành công hoặc nhân viên giao xe -> xe chuyển sang `dang_thue`.
   - Khi khách hủy đơn, đơn hết hạn 15 phút, hoặc nhân viên bấm `COMPLETED` -> hệ thống kiểm tra xe không còn đơn active nào khác rồi tự động trả `tinhTrang` về `san_sang` để sẵn sàng đón khách tiếp theo.
4. **Phân quyền bảo mật 2 tầng (Backend + Frontend):**
   - Backend sử dụng **Laravel Sanctum** kết hợp Middleware tùy chỉnh `EnsureRole` (`role:nhan_vien,admin`, `role:admin`) kiểm tra chặt vai trò từ token thực tế trên server.
   - Frontend đồng bộ Navigation Guard trong `vue-router` và hiển thị menu động theo `vaiTro`.
