<?php

namespace App\Services;

use App\Models\Xe;

class VehicleService
{
    public function searchVehicles(array $filters): array
    {
        $query = Xe::query()->where(function ($q) {
            $q->where('tinhTrang', 'san_sang')->orWhere('tinhTrang', 'Sẵn sàng');
        });

        if (! empty($filters['tenXe'])) {
            $query->where('tenXe', 'like', '%' . $filters['tenXe'] . '%');
        }

        if (! empty($filters['loaiXe'])) {
            $query->where('loaiXe', 'like', '%' . $filters['loaiXe'] . '%');
        }

        if (! empty($filters['hangXe'])) {
            $query->where('hangXe', 'like', '%' . $filters['hangXe'] . '%');
        }

        if (isset($filters['giaThue_min']) && $filters['giaThue_min'] !== '') {
            $query->where('giaThue', '>=', (float) $filters['giaThue_min']);
        }

        if (isset($filters['giaThue_max']) && $filters['giaThue_max'] !== '') {
            $query->where('giaThue', '<=', (float) $filters['giaThue_max']);
        }

        $vehicles = $query->orderBy('maXe', 'asc')->get()->map(function (Xe $vehicle) {
            return $this->formatVehicle($vehicle);
        });

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Lấy danh sách xe thành công.',
            'data' => $vehicles,
        ];
    }

    public function getVehicleDetail(string|int $maXe): array
    {
        $normalized = preg_replace('/^MX0*/i', '', (string) $maXe, 1);
        $vehicle = Xe::where('maXe', (int) $normalized)->first();

        if (! $vehicle) {
            return [
                'status' => false,
                'code' => 404,
                'message' => 'Không tìm thấy xe.',
                'data' => null,
            ];
        }

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Lấy thông tin chi tiết xe thành công.',
            'data' => $this->formatVehicle($vehicle),
        ];
    }

    private function formatVehicle(Xe $vehicle): array
    {
        $payload = $vehicle->toArray();
        $payload['maXe'] = 'MX' . str_pad((string) $vehicle->maXe, 3, '0', STR_PAD_LEFT);
        $payload['tinhTrang'] = $payload['tinhTrang'] === 'san_sang' ? 'Sẵn sàng' : $payload['tinhTrang'];

        return $payload;
    }
}

