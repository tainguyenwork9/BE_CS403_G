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
            'anhCCCDMatTruoc' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'anhCCCDMatSau' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'anhGPLX' => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
