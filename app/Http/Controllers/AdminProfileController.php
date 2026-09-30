<?php

namespace App\Http\Controllers;

use App\Models\HoSoXacThuc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminProfileController extends Controller
{
    public function getProfiles()
    {
        $profiles = DB::table('ho_so_xac_thucs as h')
            ->join('khach_hangs as k', 'k.maKhachHang', '=', 'h.maKhachHang')
            ->select(
                'h.*',
                'k.hoTen',
                'k.soDienThoai',
                'k.email'
            )
            ->orderBy('h.maHoSo', 'desc')
            ->get();

        return response()->json([
            'data' => $profiles,
        ]);
    }

    public function getPendingProfiles()
    {
        $profiles = DB::table('ho_so_xac_thucs as h')
            ->join('khach_hangs as k', 'k.maKhachHang', '=', 'h.maKhachHang')
            ->where('h.trangThai', 'cho_duyet')
            ->select(
                'h.*',
                'k.hoTen',
                'k.soDienThoai',
                'k.email'
            )
            ->orderBy('h.maHoSo', 'desc')
            ->get();

        return response()->json([
            'data' => $profiles,
        ]);
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'maHoSo' => ['required', 'integer', 'exists:ho_so_xac_thucs,maHoSo'],
            'action' => ['required', 'in:duyet,tu_choi'],
        ]);

        $profile = HoSoXacThuc::findOrFail($request->maHoSo);

        if ($request->action === 'duyet') {
            $profile->trangThai = 'da_duyet';
            $profile->ngayXacThuc = now();
        } else {
            $profile->trangThai = 'tu_choi';
            $profile->ngayXacThuc = null;
        }

        $profile->save();

        return response()->json([
            'message' => $request->action === 'duyet'
                ? 'Hồ sơ đã được duyệt.'
                : 'Hồ sơ đã bị từ chối.',
            'data' => $profile,
        ]);
    }
}
