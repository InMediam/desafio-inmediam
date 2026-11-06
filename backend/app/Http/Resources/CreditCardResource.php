<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'card_holder_name' => $this->card_holder_name,
            'card_brand' => $this->card_brand,
            'card_last_four' => $this->card_last_four,
        ];
    }
}
