<?php

namespace App\Repositories;

use App\Models\Billing;
use Illuminate\Database\Eloquent\Collection;

class BillingRepository extends AbstractRepository
{
    public function __construct()
    {
        $this->model = app(Billing::class);
    }

    public function allWithRelations(): Collection
    {
        return $this->model->with(['plan', 'customer'])->get();
    }
}
