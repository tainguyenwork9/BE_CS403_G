<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\TaiKhoan;
use App\Models\Xe;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingService
{
    private const BANK_CODE = 'vcb';
    private const ACCOUNT_NUMBER = '9813002890';
    private const ACCOUNT_NAME = 'NGUYEN LE MINH TUAN';
    private const PAYMENT_TIMEOUT_MINUTES = 1;

    public function createBooking(TaiKhoan $taiKhoan, array $data): array
    {
        $rawCarId = (string) $data['car_id'];
        $normalizedCarId = (int) preg_replace('/^MX0*/i', '', $rawCarId, 1);

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        if ($endDate->lessThanOrEqualTo($startDate)) {
            return [
                'status' => false,
                'code' => 422,
                'message' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
                'data' => null,
            ];
        }

        return DB::transaction(function () use ($taiKhoan, $normalizedCarId, $startDate, $endDate, $data) {
            // Lock bản ghi xe để chống race condition đặt trùng cùng lúc
            $xe = Xe::where('maXe', $normalizedCarId)->lockForUpdate()->first();

            if (! $xe) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Không tìm thấy xe.',
                    'data' => null,
                ];
            }

            // Tự động giải phóng các đơn PENDING_PAYMENT đã quá hạn của xe này
            $this->expireOverdueBookingsForCar($xe->maXe);

            // Kiểm tra xe có đang bị đặt trùng khoảng thời gian hay không
            $hasOverlap = Booking::where('car_id', $xe->maXe)
                ->whereIn('status', ['PENDING_PAYMENT', 'CONFIRMED', 'PAID'])
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->where('start_date', '<', $endDate)
                        ->where('end_date', '>', $startDate);
                })
                ->lockForUpdate()
                ->exists();

            if ($hasOverlap || ! in_array($xe->tinhTrang, ['san_sang', 'Sẵn sàng'], true)) {
                return [
                    'status' => false,
                    'code' => 409,
                    'message' => 'Xe đã được đặt trong khoảng thời gian này hoặc hiện không sẵn sàng.',
                    'data' => null,
                ];
            }

            // Tính số ngày thuê (tối thiểu 1 ngày, làm tròn lên theo 24 giờ)
            $hours = max(1, $startDate->diffInHours($endDate));
            $rentalDays = max(1, (int) ceil($hours / 24));
            $calculatedAmount = $rentalDays * (float) $xe->giaThue;

            // Cộng phí các tuỳ chọn đi kèm (nếu có truyền price trong options)
            $options = $data['options'] ?? [];
            $optionsFee = 0;
            if (is_array($options)) {
                foreach ($options as $opt) {
                    if (is_array($opt) && isset($opt['price']) && is_numeric($opt['price'])) {
                        $optionsFee += (float) $opt['price'];
                    }
                }
            }

            $totalAmount = isset($data['total_amount']) && (float) $data['total_amount'] > 0
                ? (float) $data['total_amount']
                : ($calculatedAmount + $optionsFee);

            $tempTransferCode = 'THUEXE_TMP_' . strtoupper(Str::random(8));

            $booking = Booking::create([
                'user_id' => $taiKhoan->maTaiKhoan,
                'car_id' => $xe->maXe,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'options' => $options,
                'total_amount' => $totalAmount,
                'status' => 'PENDING_PAYMENT',
                'transfer_code' => $tempTransferCode,
                'expires_at' => now()->addMinutes(self::PAYMENT_TIMEOUT_MINUTES),
            ]);

            $booking->transfer_code = 'THUEXE_' . $booking->id;
            $booking->save();

            return [
                'status' => true,
                'code' => 201,
                'message' => 'Tạo yêu cầu thuê xe thành công.',
                'data' => $this->formatBookingPayload($booking->fresh(['xe'])),
            ];
        });
    }

    public function getMyBookings(TaiKhoan $taiKhoan, array $filters = []): array
    {
        // Cập nhật các đơn PENDING_PAYMENT đã quá hạn của khách hàng này sang EXPIRED
        Booking::where('user_id', $taiKhoan->maTaiKhoan)
            ->where('status', 'PENDING_PAYMENT')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'EXPIRED']);

        $query = Booking::with(['xe', 'paymentTransactions'])
            ->where('user_id', $taiKhoan->maTaiKhoan)
            ->orderBy('id', 'desc');

        if (! empty($filters['status']) && $filters['status'] !== 'ALL') {
            $query->where('status', $filters['status']);
        }

        $bookings = $query->get()->map(function (Booking $booking) {
            return $this->formatBookingPayload($booking);
        });

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Lấy danh sách đơn đặt xe thành công.',
            'data' => $bookings,
        ];
    }

    public function cancelBooking(int $bookingId, TaiKhoan $taiKhoan): array
    {
        return DB::transaction(function () use ($bookingId, $taiKhoan) {
            $booking = Booking::with('xe')->where('id', $bookingId)->lockForUpdate()->first();

            if (! $booking) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Không tìm thấy đơn đặt xe.',
                    'data' => null,
                ];
            }

            if ((int) $booking->user_id !== (int) $taiKhoan->maTaiKhoan) {
                return [
                    'status' => false,
                    'code' => 403,
                    'message' => 'Bạn không có quyền hủy đơn đặt xe này.',
                    'data' => null,
                ];
            }

            if (! in_array($booking->status, ['PENDING_PAYMENT', 'PAYMENT_MISMATCH'], true)) {
                return [
                    'status' => false,
                    'code' => 409,
                    'message' => 'Chỉ có thể hủy đơn đặt xe khi đang ở trạng thái chờ thanh toán.',
                    'data' => null,
                ];
            }

            $booking->status = 'CANCELLED';
            $booking->save();

            Xe::where('maXe', $booking->car_id)
                ->where('tinhTrang', 'dang_thue')
                ->update(['tinhTrang' => 'san_sang']);

            return [
                'status' => true,
                'code' => 200,
                'message' => 'Hủy đơn đặt xe thành công.',
                'data' => $this->formatBookingPayload($booking->fresh(['xe'])),
            ];
        });
    }

    public function getBookingStatus(int $bookingId, ?TaiKhoan $taiKhoan = null): array
    {
        $booking = Booking::with(['xe', 'paymentTransactions'])->find($bookingId);

        if (! $booking) {
            return [
                'status' => false,
                'code' => 404,
                'message' => 'Không tìm thấy đơn đặt xe.',
                'data' => null,
            ];
        }

        if (
            $taiKhoan &&
            ! in_array($taiKhoan->vaiTro, ['admin', 'nhan_vien'], true) &&
            (int) $booking->user_id !== (int) $taiKhoan->maTaiKhoan
        ) {
            return [
                'status' => false,
                'code' => 403,
                'message' => 'Bạn không có quyền xem đơn đặt xe này.',
                'data' => null,
            ];
        }

        // Kiểm tra hết hạn thanh toán (timeout) -> Chuyển sang EXPIRED và giải phóng lịch xe
        if (
            $booking->status === 'PENDING_PAYMENT' &&
            $booking->expires_at &&
            now()->greaterThan($booking->expires_at)
        ) {
            DB::transaction(function () use ($booking) {
                $locked = Booking::where('id', $booking->id)->lockForUpdate()->first();
                if ($locked && $locked->status === 'PENDING_PAYMENT') {
                    $locked->status = 'EXPIRED';
                    $locked->save();

                    Xe::where('maXe', $locked->car_id)
                        ->where('tinhTrang', 'dang_thue')
                        ->update(['tinhTrang' => 'san_sang']);
                }
            });

            $booking->refresh();
        }

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Lấy trạng thái đơn đặt xe thành công.',
            'data' => $this->formatBookingPayload($booking),
        ];
    }

    public function handlePaymentWebhook(array $payload): array
    {
        // Chuẩn hóa các trường từ nhiều định dạng webhook (SePay, Casso, Custom)
        $bankTransId = (string) (
            $payload['bank_trans_id']
            ?? $payload['referenceCode']
            ?? $payload['tid']
            ?? ($payload['id'] ?? '')
        );

        $amount = (float) ($payload['amount'] ?? $payload['transferAmount'] ?? 0);
        $description = (string) (
            $payload['trans_description']
            ?? $payload['content']
            ?? $payload['description']
            ?? $payload['transfer_code']
            ?? ''
        );

        if ($bankTransId === '' || $amount <= 0) {
            return [
                'status' => false,
                'code' => 422,
                'message' => 'Dữ liệu giao dịch webhook không hợp lệ (thiếu bank_trans_id hoặc amount).',
                'data' => null,
            ];
        }

        return DB::transaction(function () use ($bankTransId, $amount, $description, $payload) {
            // 1. Chống trùng lặp webhook (Idempotency) theo bank_trans_id
            $existingTx = PaymentTransaction::where('bank_trans_id', $bankTransId)->first();
            if ($existingTx) {
                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Giao dịch đã được xử lý trước đó (Idempotent).',
                    'data' => [
                        'transaction' => $existingTx,
                        'booking' => $existingTx->booking ? $this->formatBookingPayload($existingTx->booking) : null,
                    ],
                ];
            }

            // 2. Phân tích nội dung chuyển khoản khớp với DESCRIPTION (THUEXE_{BOOKING_ID}) hoặc booking_id
            $booking = $this->resolveBookingFromWebhook($description, $payload);

            if (! $booking) {
                $tx = PaymentTransaction::create([
                    'booking_id' => null,
                    'amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                    'trans_description' => $description,
                    'status' => 'UNMATCHED',
                    'raw_payload' => $payload,
                ]);

                return [
                    'status' => false,
                    'code' => 400,
                    'message' => 'Không tìm thấy đơn đặt xe khớp với nội dung chuyển khoản.',
                    'data' => [
                        'transaction' => $tx,
                    ],
                ];
            }

            // 3. Kiểm tra đơn đã hết hạn thanh toán hay chưa
            if (
                $booking->status === 'EXPIRED' ||
                ($booking->status === 'PENDING_PAYMENT' && $booking->expires_at && now()->greaterThan($booking->expires_at))
            ) {
                $booking->status = 'EXPIRED';
                $booking->save();

                $tx = PaymentTransaction::create([
                    'booking_id' => $booking->id,
                    'amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                    'trans_description' => $description,
                    'status' => 'EXPIRED_BOOKING',
                    'raw_payload' => $payload,
                ]);

                return [
                    'status' => false,
                    'code' => 409,
                    'message' => 'Đơn đặt xe đã hết hạn thanh toán.',
                    'data' => [
                        'transaction' => $tx,
                        'booking' => $this->formatBookingPayload($booking),
                    ],
                ];
            }

            // 4. Kiểm tra số tiền chuyển vào so với total_amount (AMOUNT)
            if ($amount < (float) $booking->total_amount) {
                $booking->status = 'PAYMENT_MISMATCH';
                $booking->save();

                $tx = PaymentTransaction::create([
                    'booking_id' => $booking->id,
                    'amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                    'trans_description' => $description,
                    'status' => 'PAYMENT_MISMATCH',
                    'raw_payload' => $payload,
                ]);

                Log::warning('Cảnh báo Admin: Khách hàng chuyển sai số tiền đơn đặt xe.', [
                    'booking_id' => $booking->id,
                    'expected_amount' => $booking->total_amount,
                    'received_amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                ]);

                return [
                    'status' => false,
                    'code' => 422,
                    'message' => 'Số tiền chuyển khoản không khớp với tổng tiền đơn đặt xe (PAYMENT_MISMATCH).',
                    'data' => [
                        'transaction' => $tx,
                        'booking' => $this->formatBookingPayload($booking),
                    ],
                ];
            }

            // 5. Nếu chuyển thừa, vẫn ghi log cảnh báo cho Admin nhưng xác nhận thanh toán (>= AMOUNT)
            if ($amount > (float) $booking->total_amount) {
                Log::warning('Cảnh báo Admin: Khách hàng chuyển thừa tiền đơn đặt xe.', [
                    'booking_id' => $booking->id,
                    'expected_amount' => $booking->total_amount,
                    'received_amount' => $amount,
                    'bank_trans_id' => $bankTransId,
                ]);
            }

            // Cập nhật trạng thái đơn sang PAID và cập nhật trạng thái xe
            $booking->status = 'PAID';
            $booking->save();

            Xe::where('maXe', $booking->car_id)->update(['tinhTrang' => 'dang_thue']);

            $tx = PaymentTransaction::create([
                'booking_id' => $booking->id,
                'amount' => $amount,
                'bank_trans_id' => $bankTransId,
                'trans_description' => $description,
                'status' => 'SUCCESS',
                'raw_payload' => $payload,
            ]);

            return [
                'status' => true,
                'code' => 200,
                'message' => 'Xác nhận thanh toán tự động thành công.',
                'data' => [
                    'transaction' => $tx,
                    'booking' => $this->formatBookingPayload($booking->fresh(['xe'])),
                ],
            ];
        });
    }

    private function resolveBookingFromWebhook(string $description, array $payload): ?Booking
    {
        if (! empty($payload['booking_id'])) {
            return Booking::where('id', (int) $payload['booking_id'])->lockForUpdate()->first();
        }

        if (! empty($payload['transfer_code'])) {
            $byCode = Booking::where('transfer_code', $payload['transfer_code'])->lockForUpdate()->first();
            if ($byCode) {
                return $byCode;
            }
        }

        // Tìm mẫu THUEXE_{BOOKING_ID} hoặc THUEXE{BOOKING_ID} trong nội dung chuyển khoản
        if (preg_match('/THUEXE[_-]?(\d+)/i', $description, $matches)) {
            $bookingId = (int) $matches[1];
            $byId = Booking::where('id', $bookingId)->lockForUpdate()->first();
            if ($byId) {
                return $byId;
            }
        }

        // So khớp trực tiếp transfer_code
        $normalizedDesc = trim($description);
        if ($normalizedDesc !== '') {
            return Booking::where('transfer_code', $normalizedDesc)->lockForUpdate()->first();
        }

        return null;
    }

    private function expireOverdueBookingsForCar(int $carId): void
    {
        Booking::where('car_id', $carId)
            ->where('status', 'PENDING_PAYMENT')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'EXPIRED']);
    }

    private function formatBookingPayload(Booking $booking): array
    {
        $amount = (int) round((float) $booking->total_amount);
        $description = $booking->transfer_code;

        $qrUrl = sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.svg?amount=%s&addInfo=%s&accountName=%s',
            self::BANK_CODE,
            self::ACCOUNT_NUMBER,
            rawurlencode((string) $amount),
            rawurlencode($description),
            rawurlencode(self::ACCOUNT_NAME)
        );

        $remainingSeconds = 0;
        if ($booking->status === 'PENDING_PAYMENT' && $booking->expires_at) {
            $remainingSeconds = max(0, (int) now()->diffInSeconds($booking->expires_at, false));
        }

        return [
            'id' => $booking->id,
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'car_id' => $booking->car_id,
            'start_date' => $booking->start_date?->toISOString(),
            'end_date' => $booking->end_date?->toISOString(),
            'options' => $booking->options ?? [],
            'total_amount' => (float) $booking->total_amount,
            'status' => $booking->status,
            'is_paid' => in_array($booking->status, ['PAID', 'CONFIRMED'], true),
            'transfer_code' => $booking->transfer_code,
            'expires_at' => $booking->expires_at?->toISOString(),
            'remaining_seconds' => $remainingSeconds,
            'vehicle' => $booking->xe ? [
                'maXe' => 'MX' . str_pad((string) $booking->xe->maXe, 3, '0', STR_PAD_LEFT),
                'tenXe' => $booking->xe->tenXe,
                'hangXe' => $booking->xe->hangXe,
                'bienSo' => $booking->xe->bienSo,
                'loaiXe' => $booking->xe->loaiXe,
                'giaThue' => $booking->xe->giaThue,
                'hinhAnh' => $booking->xe->hinhAnh,
            ] : null,
            'payment_info' => [
                'bank_code' => strtoupper(self::BANK_CODE),
                'account_number' => self::ACCOUNT_NUMBER,
                'account_name' => self::ACCOUNT_NAME,
                'amount' => $amount,
                'description' => $description,
                'qr_url' => $qrUrl,
            ],
            'created_at' => $booking->created_at?->toISOString(),
            'updated_at' => $booking->updated_at?->toISOString(),
        ];
    }
}

