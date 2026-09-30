<?php

namespace App\Http\Controllers;

use App\Models\Xe;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $query = Xe::query()->where(function ($q) {
            $q->where('tinhTrang', 'san_sang')->orWhere('tinhTrang', 'Sẵn sàng');
        });

        if ($request->filled('loaiXe')) {
            $query->where('loaiXe', 'like', '%' . $request->loaiXe . '%');
        }

        if ($request->filled('hangXe')) {
            $query->where('hangXe', 'like', '%' . $request->hangXe . '%');
        }

        if ($request->filled('giaThue_min')) {
            $query->where('giaThue', '>=', (float) $request->giaThue_min);
        }

        if ($request->filled('giaThue_max')) {
            $query->where('giaThue', '<=', (float) $request->giaThue_max);
        }

        $vehicles = $query->orderBy('maXe', 'asc')->get()->map(function ($vehicle) {
            $payload = $vehicle->toArray();
            $payload['maXe'] = 'MX' . str_pad((string) $vehicle->maXe, 3, '0', STR_PAD_LEFT);
            $payload['tinhTrang'] = $payload['tinhTrang'] === 'san_sang' ? 'Sẵn sàng' : $payload['tinhTrang'];

            return $payload;
        });

        return response()->json([
            'data' => $vehicles,
        ]);
    }

    public function show($maXe)
    {
        $normalized = preg_replace('/^MX0*/', '', $maXe, 1);
        $vehicle = Xe::where('maXe', (int) $normalized)->first();

        if (! $vehicle) {
            return response()->json([
                'message' => 'Không tìm thấy xe.',
            ], 404);
        }

        $payload = $vehicle->toArray();
        $payload['maXe'] = 'MX' . str_pad((string) $vehicle->maXe, 3, '0', STR_PAD_LEFT);
        $payload['tinhTrang'] = $payload['tinhTrang'] === 'san_sang' ? 'Sẵn sàng' : $payload['tinhTrang'];

        return response()->json([
            'data' => $payload,
        ]);
    }
}