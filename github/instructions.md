
   # Quy tắc code Backend (VehicleRental_BE)
- Công nghệ: Laravel 12, MySQL, RESTful API (`routes/api.php`).
- Kiến trúc: Controller chỉ nhận Request (đã validate qua FormRequest) và gọi sang `app/Services/`. Không viết logic nghiệp vụ phức tạp trực tiếp trong Controller.
- Chuẩn JSON trả về: Luôn trả về cấu trúc thống nhất `{"status": true/false, "message": "...", "data": ...}` với đúng HTTP Status Code (200, 201, 400, 409, 422, 500).
- Database & Concurrency: Luôn đối chiếu với `docs/schema.dbml`. Đối với chức năng đặt xe (Booking), bắt buộc sử dụng `DB::transaction` kết hợp `lockForUpdate()` để chống lỗi đặt trùng xe cùng lúc (race condition).