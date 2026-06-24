<?php

namespace Tests\Unit\Repositories;

use App\Models\Customer;
use App\Models\Plan;
use App\Repositories\PlanRepository;
use Tests\TestCase;

class PlanRepositoryTest extends TestCase
{
    private PlanRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(PlanRepository::class);
    }

    public function test_all(): void
    {
        Plan::factory()->count(3)->create();

        $plans = $this->repository->all();

        $this->assertCount(3, $plans);
    }

    public function test_find(): void
    {
        $plan = Plan::factory()->create();

        $found = $this->repository->find($plan->id);

        $this->assertInstanceOf(Plan::class, $found);
        $this->assertEquals($plan->id, $found->id);
    }

    public function test_find_not_found(): void
    {
        $found = $this->repository->find(999);

        $this->assertNull($found);
    }

    public function test_find_with_select(): void
    {
        $plan = Plan::factory()->create(['name' => 'Test Plan']);

        $found = $this->repository->find($plan->id, ['id', 'name']);

        $this->assertInstanceOf(Plan::class, $found);
        $this->assertEquals('Test Plan', $found->name);
        $this->assertArrayNotHasKey('price', $found->toArray());
    }

    public function test_find_with_where(): void
    {
        Plan::factory()->create(['name' => 'Active Plan', 'active' => true]);
        Plan::factory()->create(['name' => 'Inactive Plan', 'active' => false]);

        $found = $this->repository->findFirst(['*'], [], ['active' => false]);

        $this->assertInstanceOf(Plan::class, $found);
        $this->assertEquals('Inactive Plan', $found->name);
    }

    public function test_create(): void
    {
        $plan = $this->repository->create([
            'name' => 'New Plan',
            'description' => 'Description',
            'price' => 49.90,
            'active' => true,
        ]);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('New Plan', $plan->name);
    }

    public function test_update(): void
    {
        $plan = Plan::factory()->create();

        $updated = $this->repository->update($plan->id, ['price' => 199.90]);

        $this->assertInstanceOf(Plan::class, $updated);
        $this->assertEquals(199.90, $updated->price);
    }

    public function test_update_not_found(): void
    {
        $result = $this->repository->update(999, ['name' => 'Any']);

        $this->assertNull($result);
    }

    public function test_delete(): void
    {
        $plan = Plan::factory()->create();

        $result = $this->repository->delete($plan->id);

        $this->assertTrue($result);
        $this->assertNull(Plan::find($plan->id));
    }

    public function test_delete_not_found(): void
    {
        $result = $this->repository->delete(999);

        $this->assertFalse($result);
    }

    public function test_paginated(): void
    {
        Plan::factory()->count(20)->create();

        $result = $this->repository->paginated(10, 'id', 'asc');

        $this->assertCount(10, $result->items());
        $this->assertEquals(20, $result->total());
    }

    public function test_before_create_hook(): void
    {
        $plan = $this->repository->create([
            'name' => 'Hook Test',
            'description' => 'Testing before/after hooks',
            'price' => 10.00,
            'active' => true,
        ]);

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertEquals('Hook Test', $plan->name);
    }
}
