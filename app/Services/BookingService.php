<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\HoSoXacThuc;
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

    public function createBooking(TaiKhoan $taiKhoan, array $data): array
    {
        // PB08: Kiểm tra điều kiện KYC cho khách hàng trước khi đặt xe
        if ($taiKhoan->vaiTro === 'khach_hang') {
            $khachHang = $taiKhoan->khachHang()->first();
            $hoSo = $khachHang
                ? HoSoXacThuc::where('maKhachHang', $khachHang->maKhachHang)
                    ->latest('maHoSo')
                    ->first()
                : null;

            $kycStatus = strtolower(trim((string) ($hoSo->trangThai ?? '')));
            if (! $khachHang || ! $hoSo || ! in_array($kycStatus, ['da_duyet', 'approved'], true)) {
                return [
                    'status' => false,
                    'code' => 403,
                    'message' => 'Tài khoản chưa hoàn tất xác thực CCCD/GPLX hoặc hồ sơ chưa được duyệt. Vui lòng hoàn tất xác thực trước khi đặt xe.',
                    'data' => [
                        'kyc_required' => true,
                        'kyc_status' => $hoSo?->trangThai ?? 'chua_gui',
                    ],
                ];
            }
        }

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
                ->whereIn('status', self::ACTIVE_HOLD_STATUSES)
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
            $totalSeconds = max(1, $startDate->diffInSeconds($endDate));
            $rentalDays = max(1, (int) ceil($totalSeconds / 86400));
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

        if (! empty($filters['history']) && filter_var($filters['history'], FILTER_VALIDATE_BOOLEAN)) {
            $query->whereIn('status', self::HISTORY_STATUSES);
        } elseif (! empty($filters['status']) && $filters['status'] !== 'ALL') {
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

    public function cancelBooking(int $bookingId, TaiKhoan $taiKhoan, ?string $reason = null): array
    {
        return DB::transaction(function () use ($bookingId, $taiKhoan, $reason) {
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

            // Nếu đơn PENDING_PAYMENT đã quá hạn thì cập nhật EXPIRED trước
            if (
                $booking->status === 'PENDING_PAYMENT' &&
                $booking->expires_at &&
                now()->greaterThan($booking->expires_at)
            ) {
                $booking->status = 'EXPIRED';
                $booking->save();
                $this->releaseCarIfNoActiveBooking($booking->car_id, $booking->id);

                return [
                    'status' => false,
                    'code' => 409,
                    'message' => 'Đơn đặt xe đã hết hạn thanh toán nên không thể hủy.',
                    'data' => $this->formatBookingPayload($booking->fresh(['xe'])),
                ];
            }

            $trimmedReason = trim((string) ($reason ?? ''));

            // 1. Trạng thái PENDING_PAYMENT hoặc PAYMENT_MISMATCH: hủy tự do bất cứ lúc nào
            if (in_array($booking->status, ['PENDING_PAYMENT', 'PAYMENT_MISMATCH'], true)) {
                $note = 'Khách hàng hủy đơn khi chưa hoàn tất thanh toán (không phát sinh phí hủy).';
                if ($trimmedReason !== '') {
                    $note .= ' Lý do: ' . $trimmedReason;
                }

                $booking->status = 'CANCELLED';
                $booking->ghi_chu = $note;
                $booking->save();

                $this->releaseCarIfNoActiveBooking($booking->car_id, $booking->id);

                $payload = $this->formatBookingPayload($booking->fresh(['xe']));
                $payload['refund_info'] = [
                    'refund_percent' => 0,
                    'refund_amount' => 0,
                    'policy_note' => $note,
                ];

                return [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Hủy đơn đặt xe thành công.',
                    'data' => $payload,
                ];
            }

            // 2. Trạng thái PAID hoặc CONFIRMED: áp dụng chính sách hủy đơn theo thời gian
            if (in_array($booking->status, ['PAID', 'CONFIRMED'], true)) {
                $withinFirstHour = $booking->created_at !== null
                    && now()->lessThanOrEqualTo($booking->created_at->copy()->addHour());

                $hoursUntilStart = $booking->start_date !== null
                    ? (now()->diffInSeconds($booking->start_date, false) / 3600)
                    : -1;

                if (! $withinFirstHour && $hoursUntilStart < 24) {
                    return [
                        'status' => false,
                        'code' => 409,
                        'message' => 'Không thể tự hủy đơn khi còn dưới 24 giờ trước giờ nhận xe. Vui lòng liên hệ hotline nhân viên để được hỗ trợ.',
                        'data' => null,
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

                $booking->status = 'CANCELLED';
                $booking->ghi_chu = $note;
                $booking->save();

                $this->releaseCarIfNoActiveBooking($booking->car_id, $booking->id);

                $payload = $this->formatBookingPayload($booking->fresh(['xe']));
                $payload['refund_info'] = [
                    'refund_percent' => $refundPercent,
                    'refund_amount' => $refundAmount,
                    'policy_note' => $note,
                ];

                return [
                    'status' => true,
                    'code' => 200,
                    'message' => sprintf(
                        'Hủy đơn đặt xe thành công. Mức hoàn tiền dự kiến: %d%% (%sđ).',
                        $refundPercent,
                        number_format($refundAmount, 0, ',', '.')
                    ),
                    'data' => $payload,
                ];
            }

            // 3. Các trạng thái còn lại (IN_PROGRESS, COMPLETED, CANCELLED, EXPIRED): không cho hủy
            return [
                'status' => false,
                'code' => 409,
                'message' => 'Không thể hủy đơn đặt xe ở trạng thái hiện tại (' . $booking->status . ').',
                'data' => null,
            ];
        });
    }

    public function getRebookInfo(int $bookingId, TaiKhoan $taiKhoan): array
    {
        $booking = Booking::with('xe')->find($bookingId);

        if (! $booking) {
            return [
                'status' => false,
                'code' => 404,
                'message' => 'Không tìm thấy đơn đặt xe.',
                'data' => null,
            ];
        }

        if (
            ! in_array($taiKhoan->vaiTro, ['admin', 'nhan_vien'], true) &&
            (int) $booking->user_id !== (int) $taiKhoan->maTaiKhoan
        ) {
            return [
                'status' => false,
                'code' => 403,
                'message' => 'Bạn không có quyền truy cập đơn đặt xe này.',
                'data' => null,
            ];
        }

        $xe = $booking->xe;
        if (! $xe) {
            return [
                'status' => true,
                'code' => 200,
                'message' => 'Xe trong đơn cũ hiện không còn tồn tại trong hệ thống. Vui lòng chọn xe khác.',
                'data' => [
                    'can_rebook' => false,
                    'reason' => 'Xe trong đơn cũ hiện không còn tồn tại trong hệ thống. Vui lòng chọn xe khác.',
                    'source_booking_id' => $booking->id,
                    'car_id' => null,
                    'raw_car_id' => $booking->car_id,
                    'options' => $booking->options ?? [],
                    'vehicle' => null,
                ],
            ];
        }

        $isAvailable = in_array(mb_strtolower(trim((string) $xe->tinhTrang)), ['san_sang', 'sẵn sàng'], true);
        $formattedCarId = 'MX' . str_pad((string) $xe->maXe, 3, '0', STR_PAD_LEFT);
        $vehicleLabel = $xe->tenXe . ($xe->bienSo ? ' - ' . $xe->bienSo : '');
        $unavailableReason = "Xe {$vehicleLabel} hiện không sẵn sàng để thuê lại. Vui lòng chọn xe khác hoặc thử lại sau.";

        return [
            'status' => true,
            'code' => 200,
            'message' => $isAvailable
                ? 'Xe sẵn sàng để thuê lại.'
                : $unavailableReason,
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
        ];
    }

    public function getStaffBookings(array $filters = []): array
    {
        // Tự động chuyển các đơn chờ thanh toán đã quá hạn sang EXPIRED
        Booking::where('status', 'PENDING_PAYMENT')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'EXPIRED']);

        $query = Booking::with(['xe', 'taiKhoan.khachHang', 'paymentTransactions'])
            ->orderBy('id', 'desc');

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'ALL') {
            $query->where('status', $status);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
                $q->orWhere('transfer_code', 'like', $like)
                    ->orWhereHas('xe', function ($xeQuery) use ($like) {
                        $xeQuery->where('tenXe', 'like', $like)
                            ->orWhere('bienSo', 'like', $like)
                            ->orWhere('hangXe', 'like', $like);
                    })
                    ->orWhereHas('taiKhoan', function ($tkQuery) use ($like) {
                        $tkQuery->where('tenDangNhap', 'like', $like)
                            ->orWhereHas('khachHang', function ($khQuery) use ($like) {
                                $khQuery->where('hoTen', 'like', $like)
                                    ->orWhere('soDienThoai', 'like', $like)
                                    ->orWhere('email', 'like', $like);
                            });
                    });
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 10);
        $perPage = max(1, min(50, $perPage));

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function (Booking $booking) {
            return $this->formatBookingPayload($booking, true);
        })->values();

        // Thống kê nhanh theo trạng thái cho màn hình vận hành
        $statusCounts = Booking::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'status' => true,
            'code' => 200,
            'message' => 'Lấy danh sách đơn đặt xe thành công.',
            'data' => [
                'items' => $items,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'summary' => [
                    'total' => (int) $statusCounts->sum(),
                    'PENDING_PAYMENT' => (int) ($statusCounts['PENDING_PAYMENT'] ?? 0),
                    'PAID' => (int) ($statusCounts['PAID'] ?? 0),
                    'CONFIRMED' => (int) ($statusCounts['CONFIRMED'] ?? 0),
                    'IN_PROGRESS' => (int) ($statusCounts['IN_PROGRESS'] ?? 0),
                    'COMPLETED' => (int) ($statusCounts['COMPLETED'] ?? 0),
                    'CANCELLED' => (int) ($statusCounts['CANCELLED'] ?? 0),
                    'EXPIRED' => (int) ($statusCounts['EXPIRED'] ?? 0),
                    'PAYMENT_MISMATCH' => (int) ($statusCounts['PAYMENT_MISMATCH'] ?? 0),
                ],
            ],
        ];
    }

    public function updateBookingStatusByStaff(int $bookingId, string $newStatus, ?string $note = null): array
    {
        return DB::transaction(function () use ($bookingId, $newStatus, $note) {
            $booking = Booking::with(['xe', 'taiKhoan.khachHang'])
                ->where('id', $bookingId)
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                return [
                    'status' => false,
                    'code' => 404,
                    'message' => 'Không tìm thấy đơn đặt xe.',
                    'data' => null,
                ];
            }

            $currentStatus = $booking->status;
            if (in_array($currentStatus, ['COMPLETED', 'CANCELLED', 'EXPIRED'], true) && $currentStatus !== $newStatus) {
                return [
                    'status' => false,
                    'code' => 409,
                    'message' => 'Đơn đặt xe đã kết thúc (' . $currentStatus . '), không thể chuyển sang trạng thái ' . $newStatus . '.',
                    'data' => null,
                ];
            }

            $booking->status = $newStatus;

            $trimmedNote = trim((string) ($note ?? ''));
            if ($trimmedNote !== '') {
                $booking->ghi_chu = $trimmedNote;
            } elseif ($newStatus === 'CANCELLED' && empty($booking->ghi_chu)) {
                $booking->ghi_chu = 'Nhân viên vận hành đã hủy đơn.';
            }

            $booking->save();

            // Đồng bộ tình trạng xe theo trạng thái đơn
            if (in_array($newStatus, ['CANCELLED', 'EXPIRED', 'COMPLETED'], true)) {
                $this->releaseCarIfNoActiveBooking($booking->car_id, $booking->id);
            } elseif (in_array($newStatus, ['PAID', 'CONFIRMED', 'IN_PROGRESS'], true)) {
                Xe::where('maXe', $booking->car_id)
                    ->where('tinhTrang', '!=', 'bao_tri')
                    ->update(['tinhTrang' => 'dang_thue']);
            }

            return [
                'status' => true,
                'code' => 200,
                'message' => 'Cập nhật trạng thái đơn đặt xe thành công.',
                'data' => $this->formatBookingPayload($booking->fresh(['xe', 'taiKhoan.khachHang']), true),
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

                    $this->releaseCarIfNoActiveBooking($locked->car_id, $locked->id);
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

    private function formatBookingPayload(Booking $booking, bool $includeCustomer = false): array
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

        $payload = [
            'id' => $booking->id,
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'car_id' => $booking->car_id,
            'start_date' => $booking->start_date?->toISOString(),
            'end_date' => $booking->end_date?->toISOString(),
            'options' => $booking->options ?? [],
            'total_amount' => (float) $booking->total_amount,
            'status' => $booking->status,
            'is_paid' => in_array($booking->status, ['PAID', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED'], true),
            'transfer_code' => $booking->transfer_code,
            'expires_at' => $booking->expires_at?->toISOString(),
            'remaining_seconds' => $remainingSeconds,
            'ghi_chu' => $booking->ghi_chu,
            'vehicle' => $booking->xe ? [
                'maXe' => 'MX' . str_pad((string) $booking->xe->maXe, 3, '0', STR_PAD_LEFT),
                'tenXe' => $booking->xe->tenXe,
                'hangXe' => $booking->xe->hangXe,
                'bienSo' => $booking->xe->bienSo,
                'loaiXe' => $booking->xe->loaiXe,
                'giaThue' => $booking->xe->giaThue,
                'tinhTrang' => $booking->xe->tinhTrang,
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

        if ($includeCustomer) {
            $taiKhoan = $booking->taiKhoan;
            $khachHang = $taiKhoan?->khachHang;
            $payload['customer'] = [
                'maTaiKhoan' => $taiKhoan?->maTaiKhoan ?? $booking->user_id,
                'tenDangNhap' => $taiKhoan?->tenDangNhap,
                'hoTen' => $khachHang?->hoTen ?? $taiKhoan?->tenDangNhap ?? ('Khách #' . $booking->user_id),
                'soDienThoai' => $khachHang?->soDienThoai,
                'email' => $khachHang?->email,
            ];
        }

        return $payload;
    }
}
