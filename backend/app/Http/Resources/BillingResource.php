<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\PlanResource;

class BillingResource extends JsonResource
{
    public function toArray(Request $request): array
    {   
        // Define os dados da cobrança e do plano que serão expostos pela API.
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status,
            'due_date' => $this->due_date,
            'plan' => new PlanResource($this->plan),
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'payments' => PaymentResource::collection($this->payments),
        ];
    }
}