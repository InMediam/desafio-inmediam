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
            'card_number' => ['required', 'string', 'regex:/^[0-9]{13,19}$/'],
            'card_holder_name' => ['required', 'string', 'min:3', 'max:100'],
            'expiry_date' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/([0-9]{2})$/'],
            'cvv' => ['required', 'string', 'regex:/^[0-9]{3,4}$/'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'card_number.required' => 'Informe o número do cartão.',
            'card_number.regex' => 'Informe um número de cartão válido, somente com dígitos.',
            'card_holder_name.required' => 'Informe o nome do titular.',
            'card_holder_name.min' => 'O nome do titular deve ter pelo menos 3 caracteres.',
            'expiry_date.required' => 'Informe a validade do cartão.',
            'expiry_date.regex' => 'Informe a validade no formato MM/AA.',
            'cvv.required' => 'Informe o CVV.',
            'cvv.regex' => 'Informe um CVV válido.',
            'amount.required' => 'Informe o valor do pagamento.',
            'amount.numeric' => 'O valor do pagamento deve ser numérico.',
            'amount.min' => 'O valor do pagamento deve ser maior que zero.',
        ];
    }
}
