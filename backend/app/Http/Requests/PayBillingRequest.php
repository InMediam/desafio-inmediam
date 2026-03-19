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
            'card_number' => ['required', 'string', 'regex:/^\d{13,19}$/'],
            'card_holder_name' => ['required', 'string', 'max:255'],
            'expiry_date' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
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
            'expiry_date.regex' => 'A validade deve estar no formato MM/AA.',
            'cvv.required' => 'O CVV é obrigatório.',
            'cvv.regex' => 'O CVV deve conter 3 ou 4 dígitos.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Dados de pagamento inválidos.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
