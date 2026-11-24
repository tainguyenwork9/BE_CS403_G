<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\HoSoXacThuc;
use App\Models\KhachHang;
use App\Models\Xe;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    private const BANK_CODE = 'vcb';
    private const ACCOUNT_NUMBER = '9813002890';
    private const ACCOUNT_NAME = 'NGUYEN LE MINH TUAN';
    private const PAYMENT_TIMEOUT_MINUTES = 15;

    private const ACTIVE_HOLD_STATUSES = [
        'PENDING_PAYMENT',
        'PAID',
        'CONFIRMED',
        'IN_PROGRESS',
    ];

    private const HISTORY_STATUSES = [
        'COMPLETED',
        'CANCELLED',
        'EXPIRED',
    ];

    public function index(Request $request)
    {
        $taiKhoan = $request->user();

        Booking::where('user_id', $taiKhoan->maTaiKhoan)
            ->where('status', 'PENDING_PAYMENT')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'EXPIRED']);

        $query = Booking::leftJoin('xes', 'bookings.car_id', '=', 'xes.maXe')
            ->where('bookings.user_id', $taiKhoan->maTaiKhoan)
            ->select(
                'bookings.*',
                'xes.maXe as xe_maXe',
                'xes.tenXe as xe_tenXe',
                'xes.hangXe as xe_hangXe',
                'xes.bienSo as xe_bienSo',
                'xes.loaiXe as xe_loaiXe',
                'xes.giaThue as xe_giaThue',
                'xes.tinhTrang as xe_tinhTrang',
                'xes.hinhAnh as xe_hinhAnh'
            )
            ->orderBy('bookings.id', 'desc');

        if (! empty($request->history) && filter_var($request->history, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereIn('bookings.status', self::HISTORY_STATUSES);
        } elseif (! empty($request->status) && $request->status !== 'ALL') {
            $query->where('bookings.status', $request->status);
        }

        $data = $query->get()->map(function ($row) {
            return $this->formatBookingRow($row);
        });

        return response()->json([
            'status' => 1,
            'data' => $data,
        ], 200);
    }

    public function store(Request $request)
    {
        $taiKhoan = $request->user();

        // Kiểm tra điều kiện KYC cho khách hàng trước khi đặt xe (PB08)
        if ($taiKhoan->vaiTro === 'khach_hang') {
            $khachHang = KhachHang::where('maTaiKhoan', $taiKhoan->maTaiKhoan)->first();
            $hoSo = $khachHang
                ? HoSoXacThuc::where('maKhachHang', $khachHang->maKhachHang)
                    ->orderBy('maHoSo', 'desc')
                    ->first()
                : null;

            $kycStatus = strtolower(trim((string) ($hoSo->trangThai ?? '')));
            if (! $khachHang || ! $hoSo || ! in_array($kycStatus, ['da_duyet', 'approved'], true)) {
                return response()->json([
                    'status' => 0,
                    'data' => [
                        'kyc_required' => true,
                        'kyc_status' => $hoSo?->trangThai ?? 'chua_gui',
                    ],
                    'message' => 'Tài khoản chưa hoàn tất xác thực CCCD/GPLX hoặc hồ sơ chưa được duyệt. Vui lòng hoàn tất xác thực trước khi đặt xe.',
                ], 403);
            }
        }

        $rawCarId = (string) $request->car_id;
        $normalizedCarId = (int) preg_replace('/^MX0*/i', '', $rawCarId, 1);

        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        if ($endDate->lessThanOrEqualTo($startDate)) {
            return response()->json([
                'status' => 0,
                'message' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
            ], 422);
        }

        $options = is_array($request->options) ? $request->options : [];

        $result = DB::transaction(function () use ($taiKhoan, $normalizedCarId, $startDate, $endDate, $options) {
            $xe = Xe::where('maXe', $normalizedCarId)->lockForUpdate()->first();

            if (! $xe) {
                return [
                    'code' => 404,
                    'body' => [
                        'status' => 0,
                        'message' => 'Không tìm thấy xe.',
                    ],
                ];
            }

            Booking::where('car_id', $xe->maXe)
                ->where('status', 'PENDING_PAYMENT')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->update(['status' => 'EXPIRED']);

            $hasOverlap = Booking::where('car_id', $xe->maXe)
                ->whereIn('status', self::ACTIVE_HOLD_STATUSES)
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->where('start_date', '<', $endDate)
                        ->where('end_date', '>', $startDate);
                })
                ->lockForUpdate()
                ->exists();

            if ($hasOverlap || ! in_array($xe->tinhTrang, ['san_sang', 'Sẵn sàng'], true)) {
                return [
                    'code' => 409,
                    'body' => [
                        'status' => 0,
                        'message' => 'Xe đã được đặt trong khoảng thời gian này hoặc hiện không sẵn sàng.',
                    ],
                ];
            }

            $totalSeconds = max(1, $startDate->diffInSeconds($endDate));
            $rentalDays = max(1, (int) ceil($totalSeconds / 86400));
            $calculatedAmount = $rentalDays * (float) $xe->giaThue;

            $optionsFee = 0;
            foreach ($options as $opt) {
                if (is_array($opt) && isset($opt['price']) && is_numeric($opt['price'])) {
                    $optionsFee += (float) $opt['price'];
                }
            }

            $totalAmount = $calculatedAmount + $optionsFee;
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

            Booking::where('id', $booking->id)->update([
                'transfer_code' => 'THUEXE_' . $booking->id,
            ]);

            $createdRow = $this->findJoinedBooking($booking->id);

            return [
                'code' => 200,
                'body' => [
                    'status' => 1,
                    'data' => $this->formatBookingRow($createdRow),
                    'message' => 'Tạo yêu cầu thuê xe thành công.',
                ],
            ];
        });

        return response()->json($result['body'], $result['code']);
    }

    public function cancel(Request $request, $id)
    {
        $taiKhoan = $request->user();
        $reason = $request->ghi_chu ?? $request->reason;

        $result = DB::transaction(function () use ($id, $taiKhoan, $reason) {
            $booking = Booking::where('id', (int) $id)->lockForUpdate()->first();

            if (! $booking) {
                return [
                    'code' => 404,
                    'body' => [
                        'status' => 0,
                        'message' => 'Không tìm thấy đơn đặt xe.',
                    ],
                ];
            }

            if ((int) $booking->user_id !== (int) $taiKhoan->maTaiKhoan) {
                return [
                    'code' => 403,
                    'body' => [
                        'status' => 0,
                        'message' => 'Bạn không có quyền hủy đơn đặt xe này.',
                    ],
                ];
            }

            if (
                $booking->status === 'PENDING_PAYMENT' &&
                $booking->expires_at &&
                now()->greaterThan($booking->expires_at)
            ) {
                Booking::where('id', $booking->id)->update(['status' => 'EXPIRED']);
                $this->releaseCarIfNoActiveBooking($booking->car_id, $booking->id);

                return [
                    'code' => 409,
                    'body' => [
                        'status' => 0,
                        'data' => $this->formatBookingRow($this->findJoinedBooking($booking->id)),
                        'message' => 'Đơn đặt xe đã hết hạn thanh toán nên không thể hủy.',
                    ],
                ];
            }

            $trimmedReason = trim((string) ($reason ?? ''));

            // 1. Chưa thanh toán: hủy tự do
            if (in_array($booking->status, ['PENDING_PAYMENT', 'PAYMENT_MISMATCH'], true)) {
                $note = 'Khách hàng hủy đơn khi chưa hoàn tất thanh toán (không phát sinh phí hủy).';
                if ($trimmedReason !== '') {
                    $note .= ' Lý do: ' . $trimmedReason;
                }

                Booking::where('id', $booking->id)->update([
                    'status' => 'CANCELLED',
                    'ghi_chu' => $note,
                ]);

                $this->releaseCarIfNoActiveBooking($booking->car_id, $booking->id);

                $payload = $this->formatBookingRow($this->findJoinedBooking($booking->id));
                $payload['refund_info'] = [
                    'refund_percent' => 0,
                    'refund_amount' => 0,
                    'policy_note' => $note,
                ];

                return [
                    'code' => 200,
                    'body' => [
                        'status' => 1,
                        'data' => $payload,
                        'message' => 'Hủy đơn đặt xe thành công.',
                    ],
                ];
            }

            // 2. Đã thanh toán / Đã xác nhận: áp dụng chính sách hoàn tiền
            if (in_array($booking->status, ['PAID', 'CONFIRMED'], true)) {
                $withinFirstHour = $booking->created_at !== null
                    && now()->lessThanOrEqualTo($booking->created_at->copy()->addHour());

                $hoursUntilStart = $booking->start_date !== null
                    ? (now()->diffInSeconds($booking->start_date, false) / 3600)
                    : -1;

                if (! $withinFirstHour && $hoursUntilStart < 24) {
                    return [
                        'code' => 409,
                        'body' => [
                            'status' => 0,
                            'message' => 'Không thể tự hủy đơn khi còn dưới 24 giờ trước giờ nhận xe. Vui lòng liên hệ hotline nhân viên để được hỗ trợ.',
                        ],
                    ];
                }

                if ($withinFirstHour || $hoursUntilStart >= 168) {
                    $refundPercent = 100;
                    $policyDetail = $withinFirstHour
                        ? 'Hủy trong vòng 1 giờ đầu sau khi đặt đơn — hoàn 100% tiền thuê.'
                        : 'Hủy trước giờ nhận xe từ 7 ngày trở lên — hoàn 100% tiền thuê.';
                } else {
                    $refundPercent = 70;
                    $policyDetail = 'Hủy trước giờ nhận xe tối thiểu 24 giờ — hoàn 70% tiền thuê.';
                }

                $refundAmount = round((float) $booking->total_amount * ($refundPercent / 100));
                $note = sprintf(
                    '%s Dự kiến hoàn: %sđ (%d%%).',
                    $policyDetail,
                    number_format($refundAmount, 0, ',', '.'),
                    $refundPercent
                );
                if ($trimmedReason !== '') {
                    $note .= ' Lý do khách hàng: ' . $trimmedReason;
                }

                Booking::where('id', $booking->id)->update([
                    'status' => 'CANCELLED',
                    'ghi_chu' => $note,
                ]);

                $this->releaseCarIfNoActiveBooking($booking->car_id, $booking->id);

                $payload = $this->formatBookingRow($this->findJoinedBooking($booking->id));
                $payload['refund_info'] = [
                    'refund_percent' => $refundPercent,
                    'refund_amount' => $refundAmount,
                    'policy_note' => $note,
                ];

                return [
                    'code' => 200,
                    'body' => [
                        'status' => 1,
                        'data' => $payload,
                        'message' => sprintf(
                            'Hủy đơn đặt xe thành công. Mức hoàn tiền dự kiến: %d%% (%sđ).',
                            $refundPercent,
                            number_format($refundAmount, 0, ',', '.')
                        ),
                    ],
                ];
            }

            return [
                'code' => 409,
                'body' => [
                    'status' => 0,
                    'message' => 'Không thể hủy đơn đặt xe ở trạng thái hiện tại (' . $booking->status . ').',
                ],
            ];
        });

        return response()->json($result['body'], $result['code']);
    }

    public function status(Request $request, $id)
    {
        $taiKhoan = $request->user('sanctum');
        $row = $this->findJoinedBooking((int) $id);

        if (! $row) {
            return response()->json([
                'status' => 0,
                'message' => 'Không tìm thấy đơn đặt xe.',
            ], 404);
        }

        if (
            $taiKhoan &&
            ! in_array($taiKhoan->vaiTro, ['admin', 'nhan_vien'], true) &&
            (int) $row->user_id !== (int) $taiKhoan->maTaiKhoan
        ) {
            return response()->json([
                'status' => 0,
                'message' => 'Bạn không có quyền xem đơn đặt xe này.',
            ], 403);
        }

        if (
            $row->status === 'PENDING_PAYMENT' &&
            $row->expires_at &&
            now()->greaterThan($row->expires_at)
        ) {
            DB::transaction(function () use ($row) {
                $locked = Booking::where('id', $row->id)->lockForUpdate()->first();
                if ($locked && $locked->status === 'PENDING_PAYMENT') {
                    Booking::where('id', $locked->id)->update(['status' => 'EXPIRED']);
                    $this->releaseCarIfNoActiveBooking($locked->car_id, $locked->id);
                }
            });

            $row = $this->findJoinedBooking((int) $id);
        }

        return response()->json([
            'status' => 1,
            'data' => $this->formatBookingRow($row),
            'message' => 'Lấy trạng thái đơn đặt xe thành công.',
        ], 200);
    }

    public function rebookInfo(Request $request, $id)
    {
        $taiKhoan = $request->user();
        $booking = Booking::find((int) $id);

        if (! $booking) {
            return response()->json([
                'status' => 0,
                'message' => 'Không tìm thấy đơn đặt xe.',
            ], 404);
        }

        if (
            ! in_array($taiKhoan->vaiTro, ['admin', 'nhan_vien'], true) &&
            (int) $booking->user_id !== (int) $taiKhoan->maTaiKhoan
        ) {
            return response()->json([
                'status' => 0,
                'message' => 'Bạn không có quyền truy cập đơn đặt xe này.',
            ], 403);
        }

        $xe = Xe::where('maXe', $booking->car_id)->first();
        if (! $xe) {
            return response()->json([
                'status' => 1,
                'data' => [
                    'can_rebook' => false,
                    'reason' => 'Xe trong đơn cũ hiện không còn tồn tại trong hệ thống. Vui lòng chọn xe khác.',
                    'source_booking_id' => $booking->id,
                    'car_id' => null,
                    'raw_car_id' => $booking->car_id,
                    'options' => $booking->options ?? [],
                    'vehicle' => null,
                ],
                'message' => 'Xe trong đơn cũ hiện không còn tồn tại trong hệ thống. Vui lòng chọn xe khác.',
            ], 200);
        }

        $isAvailable = in_array(mb_strtolower(trim((string) $xe->tinhTrang)), ['san_sang', 'sẵn sàng'], true);
        $formattedCarId = 'MX' . str_pad((string) $xe->maXe, 3, '0', STR_PAD_LEFT);
        $vehicleLabel = $xe->tenXe . ($xe->bienSo ? ' - ' . $xe->bienSo : '');
        $unavailableReason = "Xe {$vehicleLabel} hiện không sẵn sàng để thuê lại. Vui lòng chọn xe khác hoặc thử lại sau.";

        return response()->json([
            'status' => 1,
            'data' => [
                'can_rebook' => $isAvailable,
                'reason' => $isAvailable ? null : $unavailableReason,
                'source_booking_id' => $booking->id,
                'car_id' => $formattedCarId,
                'raw_car_id' => $xe->maXe,
                'options' => $booking->options ?? [],
                'vehicle' => [
                    'maXe' => $formattedCarId,
                    'tenXe' => $xe->tenXe,
                    'hangXe' => $xe->hangXe,
                    'bienSo' => $xe->bienSo,
                    'loaiXe' => $xe->loaiXe,
                    'giaThue' => $xe->giaThue,
                    'tinhTrang' => $xe->tinhTrang,
                    'hinhAnh' => $xe->hinhAnh,
                ],
            ],
            'message' => $isAvailable ? 'Xe sẵn sàng để thuê lại.' : $unavailableReason,
        ], 200);
    }

    private function findJoinedBooking(int $id): ?Booking
    {
        return Booking::leftJoin('xes', 'bookings.car_id', '=', 'xes.maXe')
            ->where('bookings.id', $id)
            ->select(
                'bookings.*',
                'xes.maXe as xe_maXe',
                'xes.tenXe as xe_tenXe',
                'xes.hangXe as xe_hangXe',
                'xes.bienSo as xe_bienSo',
                'xes.loaiXe as xe_loaiXe',
                'xes.giaThue as xe_giaThue',
                'xes.tinhTrang as xe_tinhTrang',
                'xes.hinhAnh as xe_hinhAnh'
            )
            ->first();
    }

    private function releaseCarIfNoActiveBooking(int $carId, int $excludeBookingId): void
    {
        $hasOtherActive = Booking::where('car_id', $carId)
            ->where('id', '!=', $excludeBookingId)
            ->whereIn('status', ['PAID', 'CONFIRMED', 'IN_PROGRESS'])
            ->exists();

        if (! $hasOtherActive) {
            Xe::where('maXe', $carId)
                ->where('tinhTrang', 'dang_thue')
                ->update(['tinhTrang' => 'san_sang']);
        }
    }

    private function formatBookingRow(Booking $row): array
    {
        $amount = (int) round((float) $row->total_amount);
        $description = $row->transfer_code;

        $qrUrl = sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.svg?amount=%s&addInfo=%s&accountName=%s',
            self::BANK_CODE,
            self::ACCOUNT_NUMBER,
            rawurlencode((string) $amount),
            rawurlencode($description),
            rawurlencode(self::ACCOUNT_NAME)
        );

        $remainingSeconds = 0;
        if ($row->status === 'PENDING_PAYMENT' && $row->expires_at) {
            $remainingSeconds = max(0, (int) now()->diffInSeconds($row->expires_at, false));
        }

        return [
            'id' => $row->id,
            'booking_id' => $row->id,
            'user_id' => $row->user_id,
            'car_id' => $row->car_id,
            'start_date' => $row->start_date?->toISOString(),
            'end_date' => $row->end_date?->toISOString(),
            'options' => $row->options ?? [],
            'total_amount' => (float) $row->total_amount,
            'status' => $row->status,
            'is_paid' => in_array($row->status, ['PAID', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED'], true),
            'transfer_code' => $row->transfer_code,
            'expires_at' => $row->expires_at?->toISOString(),
            'remaining_seconds' => $remainingSeconds,
            'ghi_chu' => $row->ghi_chu,
            'vehicle' => $row->xe_maXe ? [
                'maXe' => 'MX' . str_pad((string) $row->xe_maXe, 3, '0', STR_PAD_LEFT),
                'tenXe' => $row->xe_tenXe,
                'hangXe' => $row->xe_hangXe,
                'bienSo' => $row->xe_bienSo,
                'loaiXe' => $row->xe_loaiXe,
                'giaThue' => $row->xe_giaThue,
                'tinhTrang' => $row->xe_tinhTrang,
                'hinhAnh' => $row->xe_hinhAnh,
            ] : null,
            'payment_info' => [
                'bank_code' => strtoupper(self::BANK_CODE),
                'account_number' => self::ACCOUNT_NUMBER,
                'account_name' => self::ACCOUNT_NAME,
                'amount' => $amount,
                'description' => $description,
                'qr_url' => $qrUrl,
            ],
            'created_at' => $row->created_at?->toISOString(),
            'updated_at' => $row->updated_at?->toISOString(),
        ];
    }
}
