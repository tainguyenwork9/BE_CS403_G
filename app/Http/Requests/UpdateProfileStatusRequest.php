<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maHoSo' => ['required', 'integer', 'exists:ho_so_xac_thucs,maHoSo'],
            'action' => ['required', 'string', 'in:duyet,tu_choi'],
        ];
    }
}

