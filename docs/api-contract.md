# API Contract — Sprint 2 (PB08 - PB13): Đặt xe, Thanh toán VietQR, Quản lý đơn & Thuê lại

Mọi API đều trả về định dạng JSON chuẩn:
```json
{
  "status": true,
  "message": "string",
  "data": {}
}
```

---

## 1. Tạo đơn đặt xe (PB08)
- **Endpoint:** `POST /api/bookings`
- **Auth:** `Bearer Token` (`auth:sanctum`)
- **Ràng buộc KYC:** Nếu user có `vaiTro = khach_hang`, bắt buộc đã có bản ghi `khach_hangs` và `ho_so_xac_thucs` với `trangThai` là `da_duyet` hoặc `approved`. Nếu chưa đạt -> trả về `403 Forbidden`:
  ```json
  {
    "status": false,
    "message": "Tài khoản chưa hoàn tất xác thực CCCD/GPLX hoặc hồ sơ chưa được duyệt. Vui lòng hoàn tất xác thực trước khi đặt xe.",
    "data": { "kyc_required": true, "kyc_status": "cho_duyet" }
  }
  ```
- **Request Body:**
  ```json
  {
    "car_id": "MX001",
    "start_date": "2026-11-22T09:00:00",
    "end_date": "2026-11-24T09:00:00",
    "options": [
      { "id": "helmet_pair", "name": "2 Mũ bảo hiểm đạt chuẩn + Áo mưa", "price": 0 },
      { "id": "insurance", "name": "Gói bảo hiểm chuyến đi toàn diện", "price": 30000 }
    ]
  }
  ```
- **Logic:** Kiểm tra `Xe.tinhTrang == 'san_sang'` và không trùng lịch với các đơn ở trạng thái `PENDING_PAYMENT`, `PAID`, `CONFIRMED`, `IN_PROGRESS`. Tính tiền theo ngày 24h (`ceil(seconds / 86400)`, tối thiểu 1 ngày) + phí `options`. Tạo đơn `PENDING_PAYMENT`, mã `THUEXE_{id}`, hạn thanh toán 15 phút, link ảnh VietQR.
- **Responses:** `201 Created`, `403 Forbidden` (chưa KYC), `404 Not Found`, `409 Conflict` (trùng lịch/xe không sẵn sàng), `422 Unprocessable Entity`.

---

## 2. Kiểm tra trạng thái đơn & Thanh toán QR (PB09)
- **Endpoint:** `GET /api/bookings/{id}/status`
- **Auth:** Optional `Bearer Token` (nếu có token, chỉ chủ đơn hoặc `nhan_vien`/`admin` mới xem được)
- **Logic:** Tự động chuyển `PENDING_PAYMENT` quá hạn (`expires_at < now`) sang `EXPIRED` và giải phóng xe. Trả về đầy đủ `payment_info` (VietQR `qr_url`, `bank_code`, `account_number`, `account_name`, `amount`, `description`) và `remaining_seconds`.
- **Responses:** `200 OK`, `403 Forbidden`, `404 Not Found`.

---

## 3. Webhook Thanh toán tự động (PB09)
- **Endpoint:** `POST /api/payments/webhook`
- **Auth:** Public (Webhook từ cổng thanh toán hoặc nút giả lập trên UI)
- **Request Body:**
  ```json
  {
    "bank_trans_id": "VCB_12_1732150000000",
    "amount": 350000,
    "trans_description": "Chuyen khoan THUEXE_12",
    "booking_id": 12
  }
  ```
- **Logic:**
  - Idempotency theo `bank_trans_id`.
  - Khớp `THUEXE_{id}` / `booking_id`.
  - Nếu `amount < total_amount` -> `PAYMENT_MISMATCH` (`422`).
  - Nếu `amount >= total_amount` -> cập nhật `Booking.status = 'PAID'`, `Xe.tinhTrang = 'dang_thue'`, lưu `payment_transactions`.
- **Responses:** `200 OK`, `400 Bad Request`, `409 Conflict` (hết hạn), `422 Unprocessable Entity`.

---

## 4. Danh sách đơn cá nhân & Lịch sử thuê xe (PB10, PB13)
- **Endpoint:** `GET /api/bookings`
- **Auth:** `Bearer Token` (`auth:sanctum`)
- **Query Params:**
  - `status` (optional): `ALL` | `PENDING_PAYMENT` | `PAID` | `CONFIRMED` | `IN_PROGRESS` | `COMPLETED` | `CANCELLED` | `EXPIRED` | `PAYMENT_MISMATCH`
  - `history` (optional, boolean): `true` / `1` để lọc riêng lịch sử (`COMPLETED`, `CANCELLED`, `EXPIRED`)
- **Responses:** `200 OK` với danh sách đơn kèm `vehicle`, `payment_info`, `ghi_chu`.

---

## 5. Hủy đơn đặt xe — Khách hàng (PB10)
- **Endpoint:** `POST /api/bookings/{id}/cancel`
- **Auth:** `Bearer Token` (`auth:sanctum`)
- **Request Body (optional):**
  ```json
  {
    "ghi_chu": "Thay đổi lịch trình"
  }
  ```
- **Chính sách hủy đơn:**
  1. `PENDING_PAYMENT` / `PAYMENT_MISMATCH`: Hủy tự do, giải phóng xe về `san_sang`.
  2. `PAID` / `CONFIRMED`:
     - Hủy trong vòng 1 giờ đầu sau khi đặt HOẶC trước giờ nhận xe `start_date` >= 7 ngày: **Hoàn 100%**.
     - Hủy trước giờ nhận xe `start_date` >= 24 giờ: **Hoàn 70%**.
     - Còn dưới 24 giờ trước `start_date` (và đã quá 1 giờ sau khi đặt): Trả `409 Conflict`, yêu cầu liên hệ hotline nhân viên.
     - Giải phóng `Xe.tinhTrang = 'san_sang'` và lưu ghi chú hoàn tiền vào `ghi_chu` + trả `data.refund_info`.
  3. `IN_PROGRESS` / `COMPLETED` / `CANCELLED` / `EXPIRED`: Không cho hủy (`409 Conflict`).
- **Responses:** `200 OK`, `403 Forbidden`, `404 Not Found`, `409 Conflict`.

---

## 6. Lấy thông tin thuê lại xe (PB12)
- **Endpoint:** `GET /api/bookings/{id}/rebook-info`
- **Auth:** `Bearer Token` (`auth:sanctum`)
- **Response (`200 OK`):**
  ```json
  {
    "status": true,
    "message": "Xe sẵn sàng để thuê lại.",
    "data": {
      "can_rebook": true,
      "reason": null,
      "source_booking_id": 12,
      "car_id": "MX001",
      "raw_car_id": 1,
      "options": [
        { "id": "helmet_pair", "name": "2 Mũ bảo hiểm đạt chuẩn + Áo mưa", "price": 0 }
      ],
      "vehicle": {
        "maXe": "MX001",
        "tenXe": "Honda Air Blade 160",
        "hangXe": "Honda",
        "bienSo": "43C1-12345",
        "loaiXe": "Tay ga",
        "giaThue": 150000,
        "tinhTrang": "san_sang",
        "hinhAnh": "..."
      }
    }
  }
  ```
- Chỉ cho phép thuê lại (`can_rebook: true`) khi xe còn tồn tại và `tinhTrang === 'san_sang'`. Nếu xe đang được thuê, đang bảo trì hoặc không còn tồn tại -> `can_rebook: false` kèm `reason` cụ thể (`Xe [Tên xe - Biển số] hiện không sẵn sàng để thuê lại...`).

---

## 7. Quản lý toàn bộ đơn đặt xe — Nhân viên vận hành (PB11)
- **Phân quyền:** `auth:sanctum` + `role:nhan_vien,admin`

### 7.1. Danh sách toàn bộ đơn
- **Endpoint:** `GET /api/staff/bookings`
- **Query Params:**
  - `status` (optional): `ALL` hoặc một trạng thái cụ thể
  - `search` (optional): tìm theo mã đơn, `transfer_code`, tên khách (`hoTen`), SĐT (`soDienThoai`), tên xe (`tenXe`), biển số (`bienSo`)
  - `per_page` (optional, default `10`, max `50`)
  - `page` (optional, default `1`)
- **Response (`200 OK`):**
  ```json
  {
    "status": true,
    "message": "Lấy danh sách đơn đặt xe thành công.",
    "data": {
      "items": [
        {
          "id": 12,
          "status": "PAID",
          "transfer_code": "THUEXE_12",
          "total_amount": 300000,
          "ghi_chu": null,
          "customer": {
            "maTaiKhoan": 3,
            "tenDangNhap": "khach1",
            "hoTen": "Nguyễn Văn A",
            "soDienThoai": "0901234567",
            "email": "a@example.com"
          },
          "vehicle": { "maXe": "MX001", "tenXe": "Honda Vision", "bienSo": "43C1-12345" }
        }
      ],
      "pagination": {
        "current_page": 1,
        "last_page": 1,
        "per_page": 10,
        "total": 1
      },
      "summary": {
        "total": 1,
        "PENDING_PAYMENT": 0,
        "PAID": 1,
        "CONFIRMED": 0,
        "IN_PROGRESS": 0,
        "COMPLETED": 0,
        "CANCELLED": 0,
        "EXPIRED": 0,
        "PAYMENT_MISMATCH": 0
      }
    }
  }
  ```

### 7.2. Cập nhật trạng thái đơn
- **Endpoint:** `PATCH /api/staff/bookings/{id}/status`
- **Request Body:**
  ```json
  {
    "status": "CONFIRMED",
    "ghi_chu": "Đã xác nhận thanh toán và chuẩn bị xe giao khách."
  }
  ```
- **Logic:**
  - Không cho chuyển trạng thái đối với đơn đã kết thúc (`COMPLETED`, `CANCELLED`, `EXPIRED`) -> `409 Conflict`.
  - Khi chuyển sang `CANCELLED`, `EXPIRED`, `COMPLETED`: tự động trả `Xe.tinhTrang = 'san_sang'` nếu xe không còn đơn hoạt động nào khác.
  - Khi chuyển sang `PAID`, `CONFIRMED`, `IN_PROGRESS`: cập nhật `Xe.tinhTrang = 'dang_thue'` (nếu xe không ở trạng thái `bao_tri`).
- **Responses:** `200 OK`, `403 Forbidden`, `404 Not Found`, `409 Conflict`, `422 Unprocessable Entity`.
