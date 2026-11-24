<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenXe' => ['sometimes', 'nullable', 'string', 'max:255'],
            'loaiXe' => ['sometimes', 'nullable', 'string', 'max:255'],
            'hangXe' => ['sometimes', 'nullable', 'string', 'max:255'],
            'giaThue_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'giaThue_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}

