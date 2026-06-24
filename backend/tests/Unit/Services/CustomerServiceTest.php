<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Services\CustomerService;
use Tests\TestCase;

class CustomerServiceTest extends TestCase
{
    private CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomerService::class);
    }

    public function test_create_customer(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'document' => '12345678901',
        ];

        $customer = $this->service->create($data);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('John Doe', $customer->name);
        $this->assertEquals('john@example.com', $customer->email);
    }

    public function test_find_customer(): void
    {
        $customer = Customer::factory()->create();

        $found = $this->service->find($customer->id);

        $this->assertInstanceOf(Customer::class, $found);
        $this->assertEquals($customer->id, $found->id);
    }

    public function test_all_customers(): void
    {
        Customer::factory()->count(3)->create();

        $customers = $this->service->all();

        $this->assertCount(3, $customers);
    }

    public function test_update_customer(): void
    {
        $customer = Customer::factory()->create();

        $updated = $this->service->update($customer->id, ['name' => 'Jane Doe']);

        $this->assertInstanceOf(Customer::class, $updated);
        $this->assertEquals('Jane Doe', $updated->name);
    }

    public function test_delete_customer(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->service->delete($customer->id);

        $this->assertTrue($result);
        $this->assertNull(Customer::find($customer->id));
    }

    public function test_find_customer_not_found(): void
    {
        $found = $this->service->find(999);

        $this->assertNull($found);
    }

    public function test_paginated_customers(): void
    {
        Customer::factory()->count(20)->create();

        $result = $this->service->paginated(10, 'id', 'asc');

        $this->assertCount(10, $result->items());
        $this->assertEquals(20, $result->total());
        $this->assertEquals(2, $result->lastPage());
    }
}
