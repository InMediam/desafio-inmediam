<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PayBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_number' => ['required', 'string', 'regex:/^\d{13,16}$/'],
            'card_holder_name' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-zÀ-ÿ\s]+$/'],
            'expiry_date' => [
                'required',
                'string',
                'regex:/^\d{2}\/\d{2}$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! self::isExpiryValid((string) $value)) {
                        $fail('Cartão expirado ou data inválida.');
                    }
                },
            ],
            'cvv' => ['required', 'string', 'regex:/^\d{3}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_number.regex' => 'Número do cartão inválido.',
            'card_holder_name.regex' => 'Nome do titular deve conter apenas letras.',
            'expiry_date.regex' => 'Validade deve estar no formato MM/AA.',
            'cvv.regex' => 'CVV deve conter 3 dígitos.',
        ];
    }

    public function cardHolderName(): string
    {
        return strtoupper(trim($this->validated('card_holder_name')));
    }

    /**
     * @return array{month: string, year: string}
     */
    public function expiryParts(): array
    {
        [$month, $year] = explode('/', $this->validated('expiry_date'));

        return [
            'month' => $month,
            'year' => '20' . $year,
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => $validator->errors()->first(),
        ], 422));
    }

    private static function isExpiryValid(string $expiryDate): bool
    {
        if (! preg_match('/^(\d{2})\/(\d{2})$/', $expiryDate, $matches)) {
            return false;
        }

        $month = (int) $matches[1];
        $year = 2000 + (int) $matches[2];

        if ($month < 1 || $month > 12) {
            return false;
        }

        $now = now();
        $expiry = now()->setDate($year, $month, 1)->endOfMonth();

        return $expiry->greaterThanOrEqualTo($now->copy()->startOfMonth());
    }
}
