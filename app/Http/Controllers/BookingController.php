<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateBookingRequest;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService
    ) {}

    public function index(Request $request)
    {
        $result = $this->bookingService->getMyBookings($request->user(), $request->only(['status']));
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function store(CreateBookingRequest $request)
    {
        $result = $this->bookingService->createBooking($request->user(), $request->validated());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function cancel(Request $request, int $id)
    {
        $result = $this->bookingService->cancelBooking($id, $request->user());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }

    public function status(Request $request, int $id)
    {
        $result = $this->bookingService->getBookingStatus($id, $request->user('sanctum'));
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }
}

