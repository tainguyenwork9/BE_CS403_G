Xây dựng tính năng "Đặt xe & Thanh toán tự động qua VietQR" cho ứng dụng thuê xe với các yêu cầu và luồng xử lý chi tiết như sau:

---

### 1. Luồng nghiệp vụ (Business Flow)

1. **Khách hàng đã xác thực (Authenticated User):**
   - Đã đăng nhập, chọn xe muốn thuê, chọn thời gian (bắt đầu - kết thúc) và các tuỳ chọn đi kèm.
   - Bấm nút "Tạo yêu cầu thuê xe" -> Hệ thống tạo bản ghi đơn đặt xe (`Booking`) với trạng thái ban đầu: `PENDING_PAYMENT`.

2. **Khởi tạo thanh toán & Render mã VietQR:**
   - Hệ thống tính toán tổng tiền thanh toán (`AMOUNT`).
   - Tạo mã nội dung chuyển khoản duy nhất (`DESCRIPTION`), ví dụ: `THUEXE_{BOOKING_ID}` hoặc chuỗi alphanumeric ngẫu nhiên ngắn gọn để đối soát.
   - Hiển thị màn hình thanh toán gồm:
     - Thông tin tóm tắt đơn xe (tên xe, thời gian thuê, tổng tiền).
     - Mã QR động lấy trực tiếp từ URL API VietQR:
       `https://img.vietqr.io/image/vcb-9813002890-compact2.svg?amount=<AMOUNT>&addInfo=<DESCRIPTION>&accountName=NGUYEN%20LE%20MINH%20TUAN`
       *(Lưu ý: encode URL cho `amount` và `addInfo` khi binding dữ liệu)*.
     - Nút copy nhanh: Số tài khoản, Số tiền, Nội dung chuyển khoản.
     - Bộ đếm ngược thời gian thanh toán (Countdown timer, ví dụ: 10–15 phút).

3. **Cơ chế tự động xác nhận giao dịch (Auto Confirm):**
   - **Phía Backend:** Cung cấp webhook tiếp nhận thông báo biến động số dư từ cổng thanh toán/ngân hàng/dịch vụ trung gian (như SePAY, Casso,...).
     - Kiểm tra số tiền chuyển vào >= `AMOUNT`.
     - Phân tích nội dung chuyển khoản khớp với `DESCRIPTION` / `BOOKING_ID`.
     - Cập nhật trạng thái đơn đặt xe từ `PENDING_PAYMENT` sang `CONFIRMED` / `PAID`.
   - **Phía Frontend:** 
     - Lắng nghe trạng thái đơn theo thời gian thực (qua WebSocket, Server-Sent Events, hoặc Short Polling mỗi 3–5 giây).
     - Khi đơn chuyển sang `PAID`, giao diện tự động redirect sang màn hình "Đặt xe thành công" kèm hiệu ứng và thông tin hợp đồng/nhận xe.

---

### 2. Yêu cầu kỹ thuật & Data Model

- **Entities / Schema đề xuất:**
  - `Booking`: `id`, `user_id`, `car_id`, `start_date`, `end_date`, `total_amount`, `status` (`PENDING_PAYMENT`, `CONFIRMED`, `CANCELLED`, `EXPIRED`), `transfer_code`.
  - `PaymentTransaction`: `id`, `booking_id`, `amount`, `bank_trans_id`, `trans_description`, `status`, `created_at`.

- **Xử lý ngoại lệ (Edge cases):**
  - Khách hàng chuyển sai số tiền (thừa/thiếu): Chuyển trạng thái sang `PAYMENT_MISMATCH` và gửi cảnh báo tới admin.
  - Hết hạn thanh toán (timeout): Chuyển đơn sang `EXPIRED`, giải phóng lịch trống của xe.
  - Chống trùng lặp webhook (Idempotency): Xử lý trùng `bank_trans_id`.

---

### 3. Yêu cầu đầu ra (Deliverables)

Hãy sinh mã nguồn hoàn chỉnh bao gồm:
1. **Frontend Component:** Trang thanh toán hiển thị QR VietQR, thông tin chuyển khoản, countdown timer và cơ chế auto-check status.
2. **Backend API:** 
   - Endpoint tạo đơn đặt xe (`POST /api/bookings`).
   - Endpoint kiểm tra trạng thái đơn (`GET /api/bookings/:id/status`).
   - Endpoint Webhook xử lý biến động số dư (`POST /api/payments/webhook`).