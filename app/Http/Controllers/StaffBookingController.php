<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Xe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffBookingController extends Controller
{
    private const BANK_CODE = 'vcb';
    private const ACCOUNT_NUMBER = '9813002890';
    private const ACCOUNT_NAME = 'NGUYEN LE MINH TUAN';

    private const ALLOWED_STATUSES = [
        'PENDING_PAYMENT',
        'PAID',
        'CONFIRMED',
        'IN_PROGRESS',
        'COMPLETED',
        'CANCELLED',
        'EXPIRED',
        'PAYMENT_MISMATCH',
    ];

    public function index(Request $request)
    {
        // Tự động hết hạn các đơn quá hạn thanh toán hoặc quá thời gian trả xe mà chưa duyệt/bàn giao
        $this->expireOverdueBookings();

        $query = Booking::leftJoin('xes', 'bookings.car_id', '=', 'xes.maXe')
            ->leftJoin('tai_khoans', 'bookings.user_id', '=', 'tai_khoans.maTaiKhoan')
            ->leftJoin('khach_hangs', 'tai_khoans.maTaiKhoan', '=', 'khach_hangs.maTaiKhoan')
            ->select(
                'bookings.*',
                'xes.maXe as xe_maXe',
                'xes.tenXe as xe_tenXe',
                'xes.hangXe as xe_hangXe',
                'xes.bienSo as xe_bienSo',
                'xes.loaiXe as xe_loaiXe',
                'xes.giaThue as xe_giaThue',
                'xes.tinhTrang as xe_tinhTrang',
                'xes.hinhAnh as xe_hinhAnh',
                'tai_khoans.maTaiKhoan as tk_maTaiKhoan',
                'tai_khoans.tenDangNhap as tk_tenDangNhap',
                'khach_hangs.hoTen as kh_hoTen',
                'khach_hangs.soDienThoai as kh_soDienThoai',
                'khach_hangs.email as kh_email'
            )
            ->orderBy('bookings.id', 'desc');

        $status = trim((string) ($request->status ?? ''));
        if ($status !== '' && $status !== 'ALL') {
            $query->where('bookings.status', $status);
        }

        $search = trim((string) ($request->search ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($search, $like) {
                if (is_numeric($search)) {
                    $q->orWhere('bookings.id', (int) $search);
                }
                $q->orWhere('bookings.transfer_code', 'like', $like)
                    ->orWhere('xes.tenXe', 'like', $like)
                    ->orWhere('xes.bienSo', 'like', $like)
                    ->orWhere('xes.hangXe', 'like', $like)
                    ->orWhere('tai_khoans.tenDangNhap', 'like', $like)
                    ->orWhere('khach_hangs.hoTen', 'like', $like)
                    ->orWhere('khach_hangs.soDienThoai', 'like', $like)
                    ->orWhere('khach_hangs.email', 'like', $like);
            });
        }

        $perPage = max(1, min(50, (int) ($request->per_page ?? 10)));
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function ($row) {
            return $this->formatStaffRow($row);
        })->values();

        $statusCounts = Booking::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'status' => 1,
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
        ], 200);
    }

    public function updateStatus(Request $request, $id = null)
    {
        $bookingId = (int) ($request->id ?? $id);
        $newStatus = (string) $request->status;
        $note = $request->ghi_chu ?? $request->reason;

        if (! in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            return response()->json([
                'status' => 0,
                'message' => 'Trạng thái đơn đặt xe không hợp lệ.',
            ], 422);
        }

        $result = DB::transaction(function () use ($bookingId, $newStatus, $note) {
            $booking = Booking::where('id', $bookingId)->lockForUpdate()->first();

            if (! $booking) {
                return [
                    'code' => 404,
                    'body' => [
                        'status' => 0,
                        'message' => 'Không tìm thấy đơn đặt xe.',
                    ],
                ];
            }

            $isOverdueUnapproved = in_array($booking->status, ['PENDING_PAYMENT', 'PAYMENT_MISMATCH', 'PAID', 'CONFIRMED'], true)
                && $booking->end_date
                && now()->greaterThan($booking->end_date);

            $isOverduePayment = $booking->status === 'PENDING_PAYMENT'
                && $booking->expires_at
                && now()->greaterThan($booking->expires_at);

            if ($isOverdueUnapproved || $isOverduePayment) {
                Booking::where('id', $booking->id)->update([
                    'status' => 'EXPIRED',
                    'ghi_chu' => $booking->ghi_chu ?: 'Đơn đã quá thời gian trả xe nhưng chưa được duyệt/bàn giao nên tự động hết hạn.',
                ]);
                $this->releaseCarIfNoActiveBooking($booking->car_id, $booking->id);

                return [
                    'code' => 409,
                    'body' => [
                        'status' => 0,
                        'message' => 'Đơn đặt xe đã quá thời gian quy định và tự động chuyển sang Hết hạn (EXPIRED), không thể thao tác thêm.',
                    ],
                ];
            }

            if (in_array($booking->status, ['COMPLETED', 'CANCELLED', 'EXPIRED'], true)) {
                return [
                    'code' => 409,
                    'body' => [
                        'status' => 0,
                        'message' => 'Đơn đặt xe đã kết thúc (' . $booking->status . '), không thể thao tác thêm.',
                    ],
                ];
            }

            $trimmedNote = trim((string) ($note ?? ''));
            $ghiChu = $booking->ghi_chu;
            if ($trimmedNote !== '') {
                $ghiChu = $trimmedNote;
            } elseif ($newStatus === 'CANCELLED' && empty($ghiChu)) {
                $ghiChu = 'Nhân viên vận hành đã hủy đơn.';
            }

            Booking::where('id', $booking->id)->update([
                'status' => $newStatus,
                'ghi_chu' => $ghiChu,
            ]);

            if (in_array($newStatus, ['CANCELLED', 'EXPIRED', 'COMPLETED'], true)) {
                $hasOtherActive = Booking::where('car_id', $booking->car_id)
                    ->where('id', '!=', $booking->id)
                    ->whereIn('status', ['PAID', 'CONFIRMED', 'IN_PROGRESS'])
                    ->exists();

                if (! $hasOtherActive) {
                    Xe::where('maXe', $booking->car_id)
                        ->where('tinhTrang', 'dang_thue')
                        ->update(['tinhTrang' => 'san_sang']);
                }
            } elseif (in_array($newStatus, ['PAID', 'CONFIRMED', 'IN_PROGRESS'], true)) {
                Xe::where('maXe', $booking->car_id)
                    ->where('tinhTrang', '!=', 'bao_tri')
                    ->update(['tinhTrang' => 'dang_thue']);
            }

            $updatedRow = Booking::leftJoin('xes', 'bookings.car_id', '=', 'xes.maXe')
                ->leftJoin('tai_khoans', 'bookings.user_id', '=', 'tai_khoans.maTaiKhoan')
                ->leftJoin('khach_hangs', 'tai_khoans.maTaiKhoan', '=', 'khach_hangs.maTaiKhoan')
                ->where('bookings.id', $booking->id)
                ->select(
                    'bookings.*',
                    'xes.maXe as xe_maXe',
                    'xes.tenXe as xe_tenXe',
                    'xes.hangXe as xe_hangXe',
                    'xes.bienSo as xe_bienSo',
                    'xes.loaiXe as xe_loaiXe',
                    'xes.giaThue as xe_giaThue',
                    'xes.tinhTrang as xe_tinhTrang',
                    'xes.hinhAnh as xe_hinhAnh',
                    'tai_khoans.maTaiKhoan as tk_maTaiKhoan',
                    'tai_khoans.tenDangNhap as tk_tenDangNhap',
                    'khach_hangs.hoTen as kh_hoTen',
                    'khach_hangs.soDienThoai as kh_soDienThoai',
                    'khach_hangs.email as kh_email'
                )
                ->first();

            return [
                'code' => 200,
                'body' => [
                    'status' => 1,
                    'data' => $this->formatStaffRow($updatedRow),
                    'message' => 'Cập nhật trạng thái đơn đặt xe thành công.',
                ],
            ];
        });

        return response()->json($result['body'], $result['code']);
    }

    private function expireOverdueBookings(): void
    {
        $overdueBookings = Booking::where(function ($q) {
            $q->where(function ($q1) {
                $q1->where('status', 'PENDING_PAYMENT')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', now());
            })->orWhere(function ($q2) {
                $q2->whereIn('status', ['PENDING_PAYMENT', 'PAYMENT_MISMATCH', 'PAID', 'CONFIRMED'])
                    ->whereNotNull('end_date')
                    ->where('end_date', '<', now());
            });
        })->get();

        foreach ($overdueBookings as $item) {
            $note = $item->ghi_chu;
            if (empty($note) && $item->end_date && now()->greaterThan($item->end_date)) {
                $note = 'Đơn đã quá thời gian trả xe nhưng chưa được duyệt/bàn giao nên tự động hết hạn.';
            }

            Booking::where('id', $item->id)->update([
                'status' => 'EXPIRED',
                'ghi_chu' => $note,
            ]);

            $this->releaseCarIfNoActiveBooking($item->car_id, $item->id);
        }
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

    private function formatStaffRow(Booking $row): array
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
            'customer' => [
                'maTaiKhoan' => $row->tk_maTaiKhoan ?? $row->user_id,
                'tenDangNhap' => $row->tk_tenDangNhap,
                'hoTen' => $row->kh_hoTen ?? $row->tk_tenDangNhap ?? ('Khách #' . $row->user_id),
                'soDienThoai' => $row->kh_soDienThoai,
                'email' => $row->kh_email,
            ],
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
