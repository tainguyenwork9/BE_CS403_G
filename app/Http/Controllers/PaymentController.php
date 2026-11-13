<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentWebhookRequest;
use App\Services\BookingService;

class PaymentController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService
    ) {}

    public function webhook(PaymentWebhookRequest $request)
    {
        $result = $this->bookingService->handlePaymentWebhook($request->all());
        $code = $result['code'];
        unset($result['code']);

        return response()->json($result, $code);
    }
}

