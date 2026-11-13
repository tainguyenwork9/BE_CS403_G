<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadProfileDocsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'soCCCD' => ['sometimes', 'nullable', 'string', 'max:20'],
            'anhCCCDMatTruoc' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'anhCCCDMatSau' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'soGPLX' => ['sometimes', 'nullable', 'string', 'max:20'],
            'anhGPLX' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
