<?php

namespace App\Services;

use App\Models\Billing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BillingService
{
    public function paginate(): LengthAwarePaginator
    {
        // Centraliza a consulta das cobranças utilizadas pela Home.
        return Billing::query()
            ->select([
                'id',
                'plan_id',
                'customer_id',
                'amount',
                'status',
                'due_date',
            ])
            ->with([
                'plan:id,name,description,price,active',
                'customer:id,name',
            ])
            ->paginate();
    }
}