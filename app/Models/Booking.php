<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    protected $table = 'bookings';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'car_id',
        'start_date',
        'end_date',
        'options',
        'total_amount',
        'status',
        'transfer_code',
        'expires_at',
        'ghi_chu',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'options' => 'array',
        'total_amount' => 'double',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function taiKhoan(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'user_id', 'maTaiKhoan');
    }

    public function xe(): BelongsTo
    {
        return $this->belongsTo(Xe::class, 'car_id', 'maXe');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'booking_id', 'id');
    }
}

