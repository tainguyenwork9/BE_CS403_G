<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['sometimes', 'nullable'],
            'bank_trans_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'referenceCode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tid' => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'nullable', 'numeric'],
            'transferAmount' => ['sometimes', 'nullable', 'numeric'],
            'trans_description' => ['sometimes', 'nullable', 'string'],
            'content' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'booking_id' => ['sometimes', 'nullable', 'integer'],
            'transfer_code' => ['sometimes', 'nullable', 'string'],
        ];
    }
}

