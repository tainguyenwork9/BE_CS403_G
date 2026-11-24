<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchVehicleRequest;
use App\Services\VehicleService;

class VehicleController extends Controller
{
    public function __construct(
        private readonly VehicleService $vehicleService
    ) {}

    public function index(SearchVehicleRequest $request)
    {
        $result = $this->vehicleService->searchVehicles($request->validated());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function show($maXe)
    {
        $result = $this->vehicleService->getVehicleDetail($maXe);
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }
}
