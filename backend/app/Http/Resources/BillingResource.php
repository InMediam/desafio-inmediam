<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status,
            'due_date' => $this->due_date,
            'plan' => new PlanResource($this->plan),
            'payments' => PaymentResource::collection($this->payments),
        ];
    }
}
