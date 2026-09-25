<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenDangNhap' => ['required', 'string', 'min:3', 'max:50', 'unique:tai_khoans,tenDangNhap'],
            'matKhau' => ['required', 'string', 'min:6'],
            'hoTen' => ['required', 'string', 'max:255'],
            'soDienThoai' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }
}
