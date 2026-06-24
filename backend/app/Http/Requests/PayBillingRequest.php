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
            'card_holder_name' => 'required|string|max:255',
            'card_number' => 'required|string',
            'expiry_date' => 'required|string|regex:/^\d{2}\/\d{2}$/',
            'cvv' => 'required|string|size:3',
            'phone' => 'required|string',
            'postal_code' => 'required|string',
            'address_number' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'card_holder_name.required' => 'O nome do titular é obrigatório.',
            'card_holder_name.string' => 'O nome do titular deve ser texto.',
            'card_holder_name.max' => 'O nome do titular deve ter no máximo 255 caracteres.',
            'card_number.required' => 'O número do cartão é obrigatório.',
            'card_number.string' => 'O número do cartão deve ser texto.',
            'expiry_date.required' => 'A data de validade é obrigatória.',
            'expiry_date.string' => 'A data de validade deve ser texto.',
            'expiry_date.regex' => 'A data de validade deve estar no formato MM/AA.',
            'cvv.required' => 'O CVV é obrigatório.',
            'cvv.string' => 'O CVV deve ser texto.',
            'cvv.size' => 'O CVV deve ter exatamente 3 dígitos.',
            'phone.required' => 'O telefone é obrigatório.',
            'phone.string' => 'O telefone deve ser texto.',
            'postal_code.required' => 'O CEP é obrigatório.',
            'postal_code.string' => 'O CEP deve ser texto.',
            'address_number.required' => 'O número do endereço é obrigatório.',
            'address_number.string' => 'O número do endereço deve ser texto.',
        ];
    }
}
