<?php

namespace App\Services;

use App\Repositories\PlanRepository;

class PlanService extends AbstractService
{
    public function __construct()
    {
        $this->repository = app(PlanRepository::class);
    }
}
