<?php

namespace Tests\Feature;

use App\Models\Billing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_billing_details(): void
    {
        $billing = Billing::factory()->create();

        $response = $this->getJson("/api/billing/{$billing->id}");

        $response->assertOk();
        $response->assertJsonFragment(['id' => $billing->id]);
    }

    public function test_it_returns_404_when_billing_does_not_exist(): void
    {
        $response = $this->getJson('/api/billing/999999');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_a_non_numeric_billing_id(): void
    {
        $response = $this->getJson('/api/billing/not-a-valid-id');

        $response->assertStatus(404);
    }
}
