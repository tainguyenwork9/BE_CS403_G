<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ghi_chu' => ['sometimes', 'nullable', 'string', 'max:500'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
