<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\Xe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function webhook(Request $request)
    {
        $bankTransId = (string) (
            $request->bank_trans_id
            ?? $request->referenceCode
            ?? $request->tid
            ?? ($request->id ?? '')
        );

        $amount = (float) ($request->amount ?? $request->transferAmount ?? 0);
        $description = (string) (
            $request->trans_description
            ?? $request->content
            ?? $request->description
            ?? $request->transfer_code
            ?? ''
        );

        if ($bankTransId === '' || $amount <= 0) {
            return response()->json([
                'status' => 0,
                'message' => 'Dữ liệu giao dịch webhook không hợp lệ (thiếu bank_trans_id hoặc amount).',
            ], 422);
        }

        $rawPayload = [
            'bank_trans_id' => $bankTransId,
            'amount' => $amount,
            'trans_description' => $description,
            'booking_id' => $request->booking_id,
            'transfer_code' => $request->transfer_code,
        ];

        $result = DB::transaction(function () use ($bankTransId, $amount, $description, $request, $rawPayload) {
            // 1. Chống trùng lặp webhook (Idempotency) theo bank_trans_id
            $existingTx = PaymentTransaction::where('bank_trans_id', $bankTransId)->first();
            if ($existingTx) {
                $existingBooking = $existingTx->booking_id ? Booking::find($existingTx->booking_id) : null;

                return [
                    'code' => 200,
                    'body' => [
                        'status' => 1,
                        'data' => [
                            'transaction' => $existingTx,
                            'booking' => $existingBooking,
                        ],
                        'message' => 'Giao dịch đã được xử lý trước đó (Idempotent).',
                    ],
                ];
            }

            // 2. Tìm đơn đặt xe khớp với booking_id / transfer_code / THUEXE_{id}
            $booking = $this->resolveBooking($description, $request);

            if (! $booking) {
                $tx = PaymentTransaction::create([
                    'booking_id' => null,
                    'amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                    'trans_description' => $description,
                    'status' => 'UNMATCHED',
                    'raw_payload' => $rawPayload,
                ]);

                return [
                    'code' => 400,
                    'body' => [
                        'status' => 0,
                        'data' => ['transaction' => $tx],
                        'message' => 'Không tìm thấy đơn đặt xe khớp với nội dung chuyển khoản.',
                    ],
                ];
            }

            // 3. Kiểm tra đơn đã hết hạn thanh toán hoặc quá thời gian trả xe hay chưa
            if (
                $booking->status === 'EXPIRED' ||
                ($booking->status === 'PENDING_PAYMENT' && $booking->expires_at && now()->greaterThan($booking->expires_at)) ||
                (in_array($booking->status, ['PENDING_PAYMENT', 'PAYMENT_MISMATCH', 'PAID', 'CONFIRMED'], true) && $booking->end_date && now()->greaterThan($booking->end_date))
            ) {
                Booking::where('id', $booking->id)->update([
                    'status' => 'EXPIRED',
                    'ghi_chu' => $booking->ghi_chu ?: 'Đơn đã quá thời gian quy định nên tự động hết hạn.',
                ]);
                $booking = Booking::find($booking->id);

                $tx = PaymentTransaction::create([
                    'booking_id' => $booking->id,
                    'amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                    'trans_description' => $description,
                    'status' => 'EXPIRED_BOOKING',
                    'raw_payload' => $rawPayload,
                ]);

                return [
                    'code' => 409,
                    'body' => [
                        'status' => 0,
                        'data' => [
                            'transaction' => $tx,
                            'booking' => $booking,
                        ],
                        'message' => 'Đơn đặt xe đã hết hạn thanh toán.',
                    ],
                ];
            }

            // 4. Kiểm tra số tiền chuyển vào so với total_amount
            if ($amount < (float) $booking->total_amount) {
                Booking::where('id', $booking->id)->update(['status' => 'PAYMENT_MISMATCH']);
                $booking = Booking::find($booking->id);

                $tx = PaymentTransaction::create([
                    'booking_id' => $booking->id,
                    'amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                    'trans_description' => $description,
                    'status' => 'PAYMENT_MISMATCH',
                    'raw_payload' => $rawPayload,
                ]);

                Log::warning('Cảnh báo Admin: Khách hàng chuyển sai số tiền đơn đặt xe.', [
                    'booking_id' => $booking->id,
                    'expected_amount' => $booking->total_amount,
                    'received_amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                ]);

                return [
                    'code' => 422,
                    'body' => [
                        'status' => 0,
                        'data' => [
                            'transaction' => $tx,
                            'booking' => $booking,
                        ],
                        'message' => 'Số tiền chuyển khoản không khớp với tổng tiền đơn đặt xe (PAYMENT_MISMATCH).',
                    ],
                ];
            }

            if ($amount > (float) $booking->total_amount) {
                Log::warning('Cảnh báo Admin: Khách hàng chuyển thừa tiền đơn đặt xe.', [
                    'booking_id' => $booking->id,
                    'expected_amount' => $booking->total_amount,
                    'received_amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                ]);
            }

            // 5. Cập nhật trạng thái đơn sang PAID và xe sang dang_thue
            Booking::where('id', $booking->id)->update(['status' => 'PAID']);
            Xe::where('maXe', $booking->car_id)->update(['tinhTrang' => 'dang_thue']);
            $booking = Booking::find($booking->id);

            $tx = PaymentTransaction::create([
                'booking_id' => $booking->id,
                'amount' => $amount,
                'bank_trans_id' => $bankTransId,
                'trans_description' => $description,
                'status' => 'SUCCESS',
                'raw_payload' => $rawPayload,
            ]);

            return [
                'code' => 200,
                'body' => [
                    'status' => 1,
                    'data' => [
                        'transaction' => $tx,
                        'booking' => $booking,
                    ],
                    'message' => 'Xác nhận thanh toán tự động thành công.',
                ],
            ];
        });

        return response()->json($result['body'], $result['code']);
    }

    private function resolveBooking(string $description, Request $request): ?Booking
    {
        if (! empty($request->booking_id)) {
            return Booking::where('id', (int) $request->booking_id)->lockForUpdate()->first();
        }

        if (! empty($request->transfer_code)) {
            $byCode = Booking::where('transfer_code', $request->transfer_code)->lockForUpdate()->first();
            if ($byCode) {
                return $byCode;
            }
        }

        if (preg_match('/THUEXE[_-]?(\d+)/i', $description, $matches)) {
            $byId = Booking::where('id', (int) $matches[1])->lockForUpdate()->first();
            if ($byId) {
                return $byId;
            }
        }

        $normalizedDesc = trim($description);
        if ($normalizedDesc !== '') {
            return Booking::where('transfer_code', $normalizedDesc)->lockForUpdate()->first();
        }

        return null;
    }
}
