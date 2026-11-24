<?php

namespace App\Http\Controllers;

use App\Services\AdminStatisticsService;

class AdminStatisticsController extends Controller
{
    public function __construct(
        private readonly AdminStatisticsService $adminStatisticsService
    ) {}

    public function index()
    {
        $result = $this->adminStatisticsService->getStatistics();
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }
}
