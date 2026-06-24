<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class PayBillingRequest
 *
 * Responsável por validar os dados de pagamento de uma cobrança
 * via cartão de crédito antes de serem processados pelo sistema.
 */
class PayBillingRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a realizar a requisição.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de validação para processamento do pagamento.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'card_holder_name' => [
                'required',
                'string',
                'max:255'
            ],

            'card_number' => [
                'required',
                'string',
                'regex:/^\d{13,19}$/'
            ],

            'expiry_date' => [
                'required',
                'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'
            ],

            'cvv' => [
                'required',
                'digits_between:3,4'
            ]
        ];
    }

    /**
     * Mensagens personalizadas de validação.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'card_holder_name.required' => 'Informe o nome do titular.',

            'card_number.required' => 'Informe o número do cartão.',
            'card_number.regex' => 'Número do cartão inválido.',

            'expiry_date.required' => 'Informe a validade.',
            'expiry_date.regex' => 'A validade deve estar no formato MM/AA.',

            'cvv.required' => 'Informe o CVV.',
            'cvv.digits_between' => 'CVV inválido.',
        ];
    }
}