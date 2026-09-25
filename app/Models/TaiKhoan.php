<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class TaiKhoan extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'tai_khoans';

    protected $primaryKey = 'maTaiKhoan';

    public $timestamps = true;

    protected $fillable = [
        'tenDangNhap',
        'matKhau',
        'vaiTro',
        'trangThai',
    ];

    protected $hidden = [
        'matKhau',
        'remember_token',
    ];

    protected $casts = [
        'trangThai' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function khachHang(): HasOne
    {
        return $this->hasOne(KhachHang::class, 'maTaiKhoan', 'maTaiKhoan');
    }
}
