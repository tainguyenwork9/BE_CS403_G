<?php

namespace App\Http\Controllers;

use App\Models\Xe;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $query = Xe::where(function ($q) {
            $q->where('tinhTrang', 'san_sang')->orWhere('tinhTrang', 'Sẵn sàng');
        });

        if (! empty($request->tenXe)) {
            $query->where('tenXe', 'like', '%' . $request->tenXe . '%');
        }

        if (! empty($request->loaiXe)) {
            $query->where('loaiXe', 'like', '%' . $request->loaiXe . '%');
        }

        if (! empty($request->hangXe)) {
            $query->where('hangXe', 'like', '%' . $request->hangXe . '%');
        }

        if ($request->giaThue_min !== null && $request->giaThue_min !== '') {
            $query->where('giaThue', '>=', (float) $request->giaThue_min);
        }

        if ($request->giaThue_max !== null && $request->giaThue_max !== '') {
            $query->where('giaThue', '<=', (float) $request->giaThue_max);
        }

        $data = $query->orderBy('maXe', 'asc')->get()->map(function ($vehicle) {
            return $this->formatVehicle($vehicle);
        });

        return response()->json([
            'status' => 1,
            'data' => $data,
        ], 200);
    }

    public function show(Request $request, $maXe)
    {
        $normalized = preg_replace('/^MX0*/i', '', (string) $maXe, 1);
        $vehicle = Xe::where('maXe', (int) $normalized)->first();

        if (! $vehicle) {
            return response()->json([
                'status' => 0,
                'message' => 'Không tìm thấy xe.',
            ], 404);
        }

        return response()->json([
            'status' => 1,
            'data' => $this->formatVehicle($vehicle),
            'message' => 'Lấy thông tin chi tiết xe thành công.',
        ], 200);
    }

    private function formatVehicle(Xe $vehicle)
    {
        $payload = $vehicle->toArray();
        $payload['maXe'] = 'MX' . str_pad((string) $vehicle->maXe, 3, '0', STR_PAD_LEFT);
        $payload['tinhTrang'] = $payload['tinhTrang'] === 'san_sang' ? 'Sẵn sàng' : $payload['tinhTrang'];

        return $payload;
    }
}
