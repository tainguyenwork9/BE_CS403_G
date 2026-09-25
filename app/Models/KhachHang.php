<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class KhachHang extends Model
{
    protected $table = 'khach_hangs';

    protected $primaryKey = 'maKhachHang';

    public $timestamps = true;

    protected $fillable = [
        'maTaiKhoan',
        'hoTen',
        'ngaySinh',
        'soDienThoai',
        'email',
        'diaChi',
    ];

    protected $casts = [
        'ngaySinh' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function taiKhoan(): BelongsTo
    {
        return $this->belongsTo(TaiKhoan::class, 'maTaiKhoan', 'maTaiKhoan');
    }

    public function hoSoXacThuc(): HasOne
    {
        return $this->hasOne(HoSoXacThuc::class, 'maKhachHang', 'maKhachHang');
    }
}
