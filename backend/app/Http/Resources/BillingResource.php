<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'customer_id' => $this->customer_id,
            'amount' => $this->amount,
            'status' => $this->status,
            'due_date' => $this->due_date,

            'plan' => $this->whenLoaded('plan', function () {
                return [
                    'id' => $this->plan->id,
                    'name' => $this->plan->name,
                    'description' => $this->plan->description,
                    'price' => $this->plan->price,
                    'active' => $this->plan->active,
                ];
            }),

            'payments' => $this->whenLoaded('payments', function () {
                return PaymentResource::collection($this->payments);
            }),
        ];
    }
}
