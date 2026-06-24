<?php

namespace App\Repositories;

use App\Models\Plan;

class PlanRepository extends AbstractRepository
{
    public function __construct()
    {
        $this->model = app(Plan::class);
    }
}
