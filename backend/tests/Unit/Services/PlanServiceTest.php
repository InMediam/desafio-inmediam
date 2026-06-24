<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Services\PlanService;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    private PlanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PlanService::class);
    }

    public function test_create_plan(): void
    {
        $data = [
            'name' => 'Premium',
            'description' => 'Premium plan',
            'price' => 99.90,
            'active' => true,
        ];

        $plan = $this->service->create($data);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('Premium', $plan->name);
        $this->assertEquals(99.90, $plan->price);
    }

    public function test_find_plan(): void
    {
        $plan = Plan::factory()->create();

        $found = $this->service->find($plan->id);

        $this->assertInstanceOf(Plan::class, $found);
        $this->assertEquals($plan->id, $found->id);
    }

    public function test_all_plans(): void
    {
        Plan::factory()->count(3)->create();

        $plans = $this->service->all();

        $this->assertCount(3, $plans);
    }

    public function test_update_plan(): void
    {
        $plan = Plan::factory()->create();

        $updated = $this->service->update($plan->id, ['price' => 149.90]);

        $this->assertInstanceOf(Plan::class, $updated);
        $this->assertEquals(149.90, $updated->price);
    }

    public function test_delete_plan(): void
    {
        $plan = Plan::factory()->create();

        $result = $this->service->delete($plan->id);

        $this->assertTrue($result);
        $this->assertNull(Plan::find($plan->id));
    }
}
