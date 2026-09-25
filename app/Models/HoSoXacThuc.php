<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HoSoXacThuc extends Model
{
    protected $table = 'ho_so_xac_thucs';

    protected $primaryKey = 'maHoSo';

    public $timestamps = true;

    protected $fillable = [
        'maKhachHang',
        'soCCCD',
        'anhCCCDMatTruoc',
        'anhCCCDMatSau',
        'soGPLX',
        'anhGPLX',
        'trangThai',
        'ngayGui',
        'ngayXacThuc',
    ];

    protected $casts = [
        'ngayGui' => 'datetime',
        'ngayXacThuc' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function khachHang(): BelongsTo
    {
        return $this->belongsTo(KhachHang::class, 'maKhachHang', 'maKhachHang');
    }
}
