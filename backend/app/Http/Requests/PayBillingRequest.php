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
            'card_number' => preg_replace('/\s+/', '', $this->card_number ?? ''),
        ]);
    }

    public function rules(): array
    {
        return [
            'card_holder_name' => ['required', 'string', 'min:3'],
            'card_number'      => ['required', 'string', 'digits_between:13,19'],
            'expiry_date'      => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'cvv'              => ['required', 'string', 'digits_between:3,4'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'phone'            => ['required', 'string', 'digits_between:10,11'],
            'postal_code'      => ['required', 'string', 'digits:8'],
            'address_number'   => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_holder_name.required' => 'O nome do titular é obrigatório.',
            'card_holder_name.min'      => 'O nome do titular deve ter pelo menos 3 caracteres.',
            'card_number.required'      => 'O número do cartão é obrigatório.',
            'card_number.digits_between'=> 'O número do cartão deve ter entre 13 e 19 dígitos.',
            'expiry_date.required'      => 'A data de validade é obrigatória.',
            'expiry_date.regex'         => 'A data de validade deve estar no formato MM/AA.',
            'cvv.required'              => 'O CVV é obrigatório.',
            'cvv.digits_between'        => 'O CVV deve ter entre 3 e 4 dígitos.',
            'amount.required'           => 'O valor é obrigatório.',
            'amount.numeric'            => 'O valor deve ser numérico.',
            'amount.min'                => 'O valor deve ser maior que zero.',
            'phone.required'            => 'O telefone é obrigatório.',
            'phone.digits_between'      => 'O telefone deve ter entre 10 e 11 dígitos.',
            'postal_code.required'      => 'O CEP é obrigatório.',
            'postal_code.digits'        => 'O CEP deve ter 8 dígitos.',
            'address_number.required'   => 'O número do endereço é obrigatório.',
        ];
    }
}
