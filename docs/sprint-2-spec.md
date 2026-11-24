# ĐẶC TẢ KỸ THUẬT SPRINT 2 (PB08 - PB13)

## Mục tiêu Sprint 2
Hoàn thiện trọn vẹn luồng Đặt xe -> Thanh toán QR tự động -> Quản lý đơn (Khách hàng & Nhân viên vận hành) -> Thuê lại & Lịch sử thuê xe.

---

## Chi tiết từng Product Backlog Item

### PB08: Đặt xe (Khách hàng đã xác thực)
- **Điều kiện tiên quyết (Ràng buộc KYC):**
  - Khách hàng (`vaiTro = khach_hang`) bắt buộc phải có bản ghi `KhachHang` và `HoSoXacThuc` ở trạng thái đã duyệt (`trangThai` là `'da_duyet'` hoặc `'approved'`). Nếu chưa xác thực, trả về lỗi `403` với thông báo yêu cầu hoàn tất xác thực CCCD/GPLX trước khi đặt xe.
- **Kiểm tra khả dụng & Tính tiền:**
  - Kiểm tra `Xe.tinhTrang == 'san_sang'` VÀ không có đơn `Booking` nào trùng khoảng `[start_date, end_date]` ở các trạng thái đang giữ xe (`PENDING_PAYMENT`, `PAID`, `CONFIRMED`, `IN_PROGRESS`).
  - Đơn giá `giaThue` tính theo ngày (24h), làm tròn lên (`ceil(hours / 24)`), tối thiểu 1 ngày.
  - Tạo đơn với trạng thái `PENDING_PAYMENT`, sinh mã chuyển khoản `THUEXE_{id}` và link ảnh VietQR.

### PB09: Thanh toán QR & Nút giả lập Webhook trên UI
- **Backend:** Giữ nguyên luồng `handlePaymentWebhook` nhận payload chuyển khoản, khớp `THUEXE_{id}`, đối chiếu `amount >= total_amount`, cập nhật `Booking.status = 'PAID'`, `Xe.tinhTrang = 'dang_thue'`, và lưu log `PaymentTransaction`.
- **Frontend (Màn hình Thanh toán QR):**
  - Hiển thị mã QR VietQR, thông tin tài khoản, số tiền, nội dung chuyển khoản `THUEXE_{id}` và đồng hồ đếm ngược `remaining_seconds`.
  - Gọi polling API `GET /api/bookings/{id}/status` mỗi 3–5 giây để tự động cập nhật giao diện ngay khi đơn chuyển sang `PAID`.
  - **Nút Demo/Test trên UI:** Đặt nút **"Giả lập thanh toán thành công (Test Webhook)"** ngay dưới mã QR. Khi bấm, gọi trực tiếp API webhook của hệ thống với payload hợp lệ (`bank_trans_id` ngẫu nhiên, `amount = booking.total_amount`, `trans_description = booking.transfer_code`) để kích hoạt luồng xác nhận tự động.

### PB10: Quản lý đơn cá nhân & Chính sách Hủy đơn (Khách hàng)
- **Xem & Theo dõi:** Khách hàng xem danh sách đơn của mình (`GET /api/bookings`), lọc theo trạng thái (`ALL`, `PENDING_PAYMENT`, `PAID`, `CONFIRMED`, `IN_PROGRESS`, `COMPLETED`, `CANCELLED`, `EXPIRED`).
- **Chính sách Hủy đơn (Chuẩn thực tế nền tảng thuê xe):**
  1. **Trạng thái `PENDING_PAYMENT` hoặc `PAYMENT_MISMATCH`:** Khách được hủy tự do bất cứ lúc nào -> chuyển sang `CANCELLED`, giải phóng xe về `san_sang`.
  2. **Trạng thái `PAID` hoặc `CONFIRMED` (Đã thanh toán nhưng chưa nhận xe):**
     - Cho phép hủy nếu thời điểm hủy **trước giờ nhận xe dự kiến (`start_date`) tối thiểu 24 giờ** (hoặc trong vòng **1 giờ đầu tiên** ngay sau khi vừa đặt đơn thành công để tránh đặt nhầm).
     - Ghi nhận mức hoàn tiền dự kiến vào `ghi_chu` / response (ví dụ: hủy trong 1h đầu hoặc trước 7 ngày hoàn 100%; hủy trước 24h hoàn 70%; sát giờ nhận xe < 24h không cho tự hủy trên app mà báo liên hệ hotline nhân viên).
     - Chuyển `Booking.status = 'CANCELLED'` và trả `Xe.tinhTrang = 'san_sang'`.
  3. **Trạng thái `IN_PROGRESS`, `COMPLETED`, `CANCELLED`, `EXPIRED`:** Không cho phép hủy (trả về `409 Conflict`).

### PB11: Quản lý đơn (Nhân viên vận hành)
- **Phân quyền:** Chỉ user có `vaiTro` là `nhan_vien` hoặc `admin` mới truy cập được.
- **API & Chức năng:**
  - `GET /api/staff/bookings`: Lấy toàn bộ danh sách đơn trong hệ thống (kèm thông tin khách hàng `hoTen`, `soDienThoai` và thông tin xe `tenXe`, `bienSo`), hỗ trợ lọc theo `status`, tìm kiếm theo mã đơn / tên khách / biển số xe, phân trang.
  - `PATCH /api/staff/bookings/{id}/status`: Nhân viên cập nhật trạng thái xử lý đơn (ví dụ: xác nhận đơn `CONFIRMED`, hoặc hủy đơn `CANCELLED` kèm lý do và tự động mở lại `Xe.tinhTrang = 'san_sang'`).

### PB12: Thuê lại (Re-book)
- **Luồng nghiệp vụ:**
  - Tại màn hình chi tiết đơn hoặc lịch sử đơn đã `COMPLETED` hoặc `CANCELLED`, hiển thị nút **"Thuê lại xe này"**.
  - Khi bấm, Frontend kiểm tra nhanh tình trạng xe hiện tại (hoặc gọi API `GET /api/bookings/{id}/rebook-info`).
  - Nếu xe vẫn đang kinh doanh (`tinhTrang != 'bao_tri'`), điều hướng sang trang Đặt xe và tự động điền sẵn `car_id` cùng các `options` của đơn cũ, chỉ yêu cầu khách chọn `start_date` và `end_date` mới. Nếu xe đang bảo trì hoặc không còn tồn tại, hiển thị cảnh báo thân thiện.

### PB13: Xem lịch sử thuê xe
- **Luồng nghiệp vụ:**
  - Cung cấp giao diện/tab **"Lịch sử thuê xe"** dành riêng cho khách hàng, hiển thị các đơn đã kết thúc vòng đời (`COMPLETED`, `CANCELLED`, `EXPIRED`).
  - Mỗi thẻ đơn hiển thị rõ: ảnh xe, tên xe, biển số, thời gian thuê (`start_date` -> `end_date`), tổng tiền đã thanh toán, trạng thái cuối cùng, và nút **"Xem chi tiết"** + **"Thuê lại"**.