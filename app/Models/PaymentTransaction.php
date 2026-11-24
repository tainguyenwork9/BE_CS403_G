<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'booking_id',
        'amount',
        'bank_trans_id',
        'trans_description',
        'status',
        'raw_payload',
    ];

    protected $casts = [
        'amount' => 'double',
        'raw_payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'booking_id', 'id');
    }
}

