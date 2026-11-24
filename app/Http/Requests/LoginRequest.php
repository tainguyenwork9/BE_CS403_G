<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenDangNhap' => ['required', 'string'],
            'matKhau' => ['required', 'string'],
        ];
    }
}