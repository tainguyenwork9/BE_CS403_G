<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Xe extends Model
{
    protected $table = 'xes';

    protected $primaryKey = 'maXe';

    public $timestamps = true;

    protected $fillable = [
        'tenXe',
        'hangXe',
        'bienSo',
        'mauSac',
        'namSanXuat',
        'giaThue',
        'loaiXe',
        'tinhTrang',
        'hinhAnh',
    ];

    protected $casts = [
        'namSanXuat' => 'integer',
        'giaThue' => 'double',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
