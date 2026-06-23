<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_holder_name' => ['required', 'string', 'max:255', 'min:3'],
            'card_number'      => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            'expiry_date'      => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/[0-9]{2}$/'],
            'cvv'              => ['required', 'string', 'regex:/^[0-9]{3}$/'],
        ];
    }
}