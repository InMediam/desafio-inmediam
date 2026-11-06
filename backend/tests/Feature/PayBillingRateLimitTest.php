<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayBillingRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_429_after_10_requests_per_minute_from_the_same_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/billing/999999/pay', []);
            $response->assertStatus(422);
        }

        $response = $this->postJson('/api/billing/999999/pay', []);

        $response->assertStatus(429);
    }
}
