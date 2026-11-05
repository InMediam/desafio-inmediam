<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class PayBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'card_number' => preg_replace('/\s+/', '', (string) $this->card_number),
        ]);
    }

    public function rules(): array
    {
        return [
            'card_number' => [
                'required',
                'string',
                'regex:/^\d{13,19}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->passesLuhnCheck($value)) {
                        $fail('O número do cartão é inválido.');
                    }
                },
            ],
            'card_holder_name' => ['required', 'string'],
            'expiry_date' => [
                'required',
                'string',
                'regex:/^(0[1-9]|1[0-2])\/\d{2}$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->parseExpiryDate($value)->isPast()) {
                        $fail('O cartão informado está vencido.');
                    }
                },
            ],
            'cvv' => ['required', 'string', 'regex:/^\d{3,4}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_number.required' => 'O número do cartão é obrigatório.',
            'card_number.regex' => 'O número do cartão deve conter entre 13 e 19 dígitos.',
            'card_holder_name.required' => 'O nome do titular é obrigatório.',
            'expiry_date.required' => 'A validade do cartão é obrigatória.',
            'expiry_date.regex' => 'A validade do cartão deve estar no formato MM/AA, com mês entre 01 e 12.',
            'cvv.required' => 'O código de segurança (CVV) é obrigatório.',
            'cvv.regex' => 'O código de segurança (CVV) deve ter 3 ou 4 dígitos.',
        ];
    }

    public function expiryMonth(): string
    {
        return explode('/', $this->expiry_date)[0];
    }

    public function expiryYear(): string
    {
        return '20'.explode('/', $this->expiry_date)[1];
    }

    private function parseExpiryDate(string $value): Carbon
    {
        [$month, $year] = explode('/', $value);

        return Carbon::createFromDate((int) ('20'.$year), (int) $month, 1)->endOfMonth();
    }

    private function passesLuhnCheck(string $number): bool
    {
        $sum = 0;
        $alternate = false;

        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($alternate) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}
