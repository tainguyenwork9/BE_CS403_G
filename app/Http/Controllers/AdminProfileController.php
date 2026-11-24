<?php

namespace App\Http\Controllers;

use App\Models\HoSoXacThuc;
use Illuminate\Http\Request;

class AdminProfileController extends Controller
{
    public function getProfiles()
    {
        $data = HoSoXacThuc::join('khach_hangs', 'ho_so_xac_thucs.maKhachHang', '=', 'khach_hangs.maKhachHang')
            ->select(
                'ho_so_xac_thucs.*',
                'khach_hangs.hoTen',
                'khach_hangs.soDienThoai',
                'khach_hangs.email'
            )
            ->orderBy('ho_so_xac_thucs.maHoSo', 'desc')
            ->get();

        return response()->json([
            'status' => 1,
            'data' => $data,
        ], 200);
    }

    public function getPendingProfiles()
    {
        $data = HoSoXacThuc::join('khach_hangs', 'ho_so_xac_thucs.maKhachHang', '=', 'khach_hangs.maKhachHang')
            ->where('ho_so_xac_thucs.trangThai', 'cho_duyet')
            ->select(
                'ho_so_xac_thucs.*',
                'khach_hangs.hoTen',
                'khach_hangs.soDienThoai',
                'khach_hangs.email'
            )
            ->orderBy('ho_so_xac_thucs.maHoSo', 'desc')
            ->get();

        return response()->json([
            'status' => 1,
            'data' => $data,
        ], 200);
    }

    public function updateStatus(Request $request)
    {
        $profile = HoSoXacThuc::find($request->maHoSo);
        if (! $profile) {
            return response()->json([
                'status' => 0,
                'message' => 'Không tìm thấy hồ sơ xác thực.',
            ], 404);
        }

        $trangThai = $request->action === 'duyet' ? 'da_duyet' : 'tu_choi';
        $ngayXacThuc = $request->action === 'duyet' ? now() : null;

        HoSoXacThuc::where('maHoSo', $request->maHoSo)->update([
            'trangThai' => $trangThai,
            'ngayXacThuc' => $ngayXacThuc,
        ]);

        $profile = HoSoXacThuc::find($request->maHoSo);

        return response()->json([
            'status' => 1,
            'data' => $profile,
            'message' => $request->action === 'duyet'
                ? 'Hồ sơ đã được duyệt.'
                : 'Hồ sơ đã bị từ chối.',
        ], 200);
    }
}
