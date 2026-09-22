<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_holder_name' => [
                'required',
                'string',
                'min:2',
                'max:100',
            ],

            'card_number' => [
                'required',
                'string',
                'digits_between:13,19',
                function ($attribute, $value, $fail) {
                    $sum = 0;
                    $shouldDouble = false;

                    for ($i = strlen($value) - 1; $i >= 0; $i--) {
                        $digit = (int) $value[$i];

                        if ($shouldDouble) {
                            $digit *= 2;

                            if ($digit > 9) {
                                $digit -= 9;
                            }
                        }

                        $sum += $digit;
                        $shouldDouble = ! $shouldDouble;
                    }

                    if ($sum % 10 !== 0) {
                        $fail('Informe um número de cartão válido.');
                    }
                },
            ],

            'expiry_date' => [
                'required',
                'regex:/^(0[1-9]|1[0-2])\/\d{2}$/',
                function ($attribute, $value, $fail) {
                    [$month, $year] = array_map('intval', explode('/', $value));

                    $currentYear = (int) now()->format('y');
                    $currentMonth = (int) now()->format('m');

                    if (
                        $year < $currentYear ||
                        ($year === $currentYear && $month < $currentMonth)
                    ) {
                        $fail('O cartão está expirado.');
                    }
                },
            ],

            'cvv' => [
                'required',
                'digits_between:3,4',
            ],
        ];
    }
}
