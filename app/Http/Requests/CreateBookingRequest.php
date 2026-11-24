<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'car_id' => ['required'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'options' => ['sometimes', 'nullable', 'array'],
            'total_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}

