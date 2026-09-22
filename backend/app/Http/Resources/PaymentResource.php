<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'billing_id' => $this->billing_id,
            'credit_card_id' => $this->credit_card_id,
            'amount_paid' => $this->amount_paid,
            'status' => $this->status,
            'paid_at' => $this->paid_at,

            'credit_card' => $this->whenLoaded('creditCard', function () {
                return [
                    'id' => $this->creditCard->id,
                    'card_holder_name' => $this->creditCard->card_holder_name,
                    'card_last_four' => $this->creditCard->card_last_four,
                    'card_brand' => $this->creditCard->card_brand,
                ];
            }),
        ];
    }
}
