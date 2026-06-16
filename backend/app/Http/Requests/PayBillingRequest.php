<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'card_holder_name' => trim((string) $this->input('card_holder_name')),
            'card_number' => preg_replace('/\D+/', '', (string) $this->input('card_number')),
            'expiry_date' => trim((string) $this->input('expiry_date')),
            'cvv' => preg_replace('/\D+/', '', (string) $this->input('cvv')),
        ]);
    }

    public function rules(): array
    {
        return [
            'card_holder_name' => ['required', 'string', 'min:3', 'max:255'],
            'card_number' => ['required', 'string', 'digits_between:13,19'],
            'expiry_date' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'cvv' => ['required', 'string', 'digits_between:3,4'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_holder_name.required' => 'Informe o nome do titular do cartão.',
            'card_holder_name.min' => 'O nome do titular precisa ter pelo menos 3 caracteres.',
            'card_number.digits_between' => 'Informe um número de cartão válido.',
            'expiry_date.regex' => 'Use o formato MM/AA na validade do cartão.',
            'cvv.digits_between' => 'Informe um CVV válido.',
        ];
    }
}
