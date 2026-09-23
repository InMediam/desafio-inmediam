<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class PayBillingRequest extends FormRequest
{
    public function rules(): array
    {
        // Valida os dados do cartão antes que o pagamento seja processado.
        return [
            'card_holder_name' => 'required|string|max:255',
            'card_number' => 'required|string|digits_between:13,19',
            'expiry_date' => [
                'required',
                'string',
                'regex:/^(0[1-9]|1[0-2])\/\d{2}$/',
                function ($attribute, $value, $fail) {
                    $expiryDate = Carbon::createFromFormat('m/y', $value)->endOfMonth();

                    if ($expiryDate->isPast()) {
                        $fail('Cartão expirado');
                    }
                },
            ],
            'cvv' => 'required|string|digits_between:3,4',
        ];
    }
}
