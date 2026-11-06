<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount_paid' => $this->amount_paid,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'credit_card' => new CreditCardResource($this->creditCard),
        ];
    }
}
