# Quy chuẩn viết Laravel API Controller

Mọi API Controller trong dự án (cả sửa code cũ lẫn viết chức năng mới) BẮT BUỘC tuân thủ chính xác phong cách dưới đây.

## 1. Nguyên tắc chung
- Viết code tối giản, trực tiếp trong Controller, dễ đọc.
- **Không** dùng `FormRequest`, luôn dùng `Request $request` mặc định của Laravel.
- **Không** bọc `try-catch`, **không** viết `validate()` dài dòng, **không** tách Service/Repository cho các thao tác CRUD cơ bản.
- Tên bảng, tên cột và key trong `$request` dùng tiếng Việt không dấu dạng `snake_case`. Tên biến PHP dùng `camelCase`.

## 2. Quy chuẩn 4 hàm CRUD
- **`index()`**:
  - Nếu cần dữ liệu bảng liên kết, dùng `Model::join(...)->select('bang_chinh.*', 'bang_phu.cot as alias')->get()`.
  - Không dùng Eloquent Relationship `with()`.
- **`store(Request $request)`**:
  - Dùng `Model::create([...])`, map thủ công từng cột từ `$request->ten_cot`.
  - Không dùng `$request->all()` hay `$request->validated()`.
- **`update(Request $request)`**:
  - Lấy ID từ `$request->id` (không nhận `$id` từ URL).
  - Dùng `Model::where('id', $request->id)->update([...])`, map thủ công từng cột cần sửa.
- **`destroy(Request $request, $id)`**:
  - Nhận `$id` từ URL, tìm bằng `Model::find($id)`.
  - Nếu không tồn tại: trả về HTTP `404` với `'status' => 0`.
  - Nếu tồn tại: gọi `$bien->delete()` và trả về HTTP `200` với `'status' => 1`.

## 3. Quy chuẩn JSON Response
- Thành công luôn trả về HTTP `200` và `'status' => 1`.
- Thất bại / không tìm thấy trả về `'status' => 0` kèm `'message'`.
- Cấu trúc trả về từng hàm:
  - `index`: `status`, `data`
  - `store` / `update`: `status`, `data`, `message`
  - `destroy` (thành công): `status`, `message`
  - `destroy` (không thấy): `status`, `message` (HTTP `404`)

## 4. Các hàm nghiệp vụ ngoài CRUD
- Giữ nguyên logic nghiệp vụ cần thiết, viết gọn trực tiếp trong Controller bằng Query Builder / Model tương tự phong cách trên.
- Vẫn bắt buộc trả về đúng format JSON (`status` là `1` hoặc `0`, `data`, `message`).

## 5. Code mẫu chuẩn (Bắt buộc bám sát)

```php
class SanPhamController extends Controller
{
    public function index()
    {
        $data = SanPham::join('loai_san_phams', 'san_phams.id_loai_san_pham', '=', 'loai_san_phams.id')
            ->select('san_phams.*', 'loai_san_phams.ten as ten_loai_san_pham')
            ->get();

        return response()->json([
            'status' => 1,
            'data' => $data,
        ], 200);
    }

    public function store(Request $request)
    {
        $data = SanPham::create([
            'ten_san_pham' => $request->ten_san_pham,
            'id_loai_san_pham' => $request->id_loai_san_pham,
            'trang_thai' => $request->trang_thai,
        ]);

        return response()->json([
            'status' => 1,
            'data' => $data,
            'message' => 'San pham '.$request->ten_san_pham.' successfully',
        ], 200);
    }

    public function update(Request $request)
    {
        $sanPham = SanPham::where('id',$request->id)->update([
            'ten_san_pham' => $request->ten_san_pham,
            'trang_thai' => $request->trang_thai,
        ]);

        return response()->json([
            'status' => 1,
            'data' => $sanPham,
            'message' => 'San pham '.$request->ten_san_pham.' updated successfully',
        ], 200);
    }

    public function destroy(Request $request,$id)
    {
        $sanPham = SanPham::find($id);
        if (! $sanPham) {
            return response()->json([
                'status' => 0,
                'message' => 'San pham not found',
            ], 404);
        }

        $sanPham->delete();

        return response()->json([
            'status' => 1,
            'message' => 'San pham '.$sanPham->ten_san_pham.' deleted successfully',
        ], 200);
    }
}