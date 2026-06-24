<?php

namespace Tests\Unit\Services;

use App\Enums\BillingStatus;
use App\Models\Billing;
use App\Models\Customer;
use App\Models\Plan;
use App\Services\BillingService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    private BillingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BillingService::class);
    }

    public function test_pay_billing_not_found(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        $this->service->pay(999, [
            'card_holder_name' => 'John Doe',
            'card_number' => '4111111111111111',
            'expiry_date' => '12/28',
            'cvv' => '123',
            'phone' => '11999999999',
            'postal_code' => '01001000',
            'address_number' => '100',
        ]);
    }

    public function test_pay_success(): void
    {
        $plan = Plan::factory()->create(['price' => 100.00]);
        $customer = Customer::factory()->create();
        $billing = Billing::factory()->create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 100.00,
            'status' => BillingStatus::PENDING,
        ]);

        Http::fake([
            'sandbox.asaas.com/api/v3/customers' => Http::response([
                'id' => 'cus_123',
                'name' => $customer->name,
                'email' => $customer->email,
            ], 200),
            'sandbox.asaas.com/api/v3/payments' => Http::response([
                'id' => 'pay_123',
                'status' => 'PENDING',
            ], 200),
            'sandbox.asaas.com/api/v3/payments/*/payWithCreditCard' => Http::response([
                'id' => 'pay_123',
                'status' => 'CONFIRMED',
                'creditCard' => [
                    'creditCardNumber' => '1234',
                    'creditCardBrand' => 'Visa',
                    'creditCardToken' => 'tok_abc',
                ],
            ], 200),
        ]);

        $result = $this->service->pay($billing->id, [
            'card_holder_name' => 'John Doe',
            'card_number' => '4111111111111111',
            'expiry_date' => '12/28',
            'cvv' => '123',
            'phone' => '11999999999',
            'postal_code' => '01001000',
            'address_number' => '100',
        ]);

        $this->assertInstanceOf(\App\Models\Payment::class, $result);
        $this->assertEquals(\App\Enums\PaymentStatus::CONFIRMED, $result->status);
        $this->assertEquals(100.00, $result->amount_paid);

        $billing->refresh();
        $this->assertEquals(BillingStatus::PAID, $billing->status);
    }

    public function test_pay_validation_fails(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->pay(1, []);
    }

    public function test_pay_asaas_fails(): void
    {
        $plan = Plan::factory()->create(['price' => 100.00]);
        $customer = Customer::factory()->create();
        $billing = Billing::factory()->create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 100.00,
            'status' => BillingStatus::PENDING,
        ]);

        Http::fake([
            'sandbox.asaas.com/api/v3/customers' => Http::response([
                'errors' => ['name' => 'Invalid'],
            ], 400),
        ]);

        $this->expectException(Exception::class);

        $this->service->pay($billing->id, [
            'card_holder_name' => 'John Doe',
            'card_number' => '4111111111111111',
            'expiry_date' => '12/28',
            'cvv' => '123',
            'phone' => '11999999999',
            'postal_code' => '01001000',
            'address_number' => '100',
        ]);
    }
}
