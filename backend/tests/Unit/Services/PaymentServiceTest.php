<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentStatus;
use App\Models\Billing;
use App\Models\CreditCard;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\PaymentService;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaymentService::class);
    }

    public function test_create_payment(): void
    {
        $plan = Plan::factory()->create();
        $customer = Customer::factory()->create();
        $billing = Billing::factory()->create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
        ]);
        $creditCard = CreditCard::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $data = [
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
            'amount_paid' => 100.00,
            'status' => 'approved',
            'paid_at' => now(),
        ];

        $payment = $this->service->create($data);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(PaymentStatus::APPROVED, $payment->status);
        $this->assertEquals(100.00, $payment->amount_paid);
    }

    public function test_find_payment(): void
    {
        $plan = Plan::factory()->create();
        $customer = Customer::factory()->create();
        $billing = Billing::factory()->create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
        ]);
        $creditCard = CreditCard::factory()->create(['customer_id' => $customer->id]);
        $payment = Payment::factory()->create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
        ]);

        $found = $this->service->find($payment->id);

        $this->assertInstanceOf(Payment::class, $found);
        $this->assertEquals($payment->id, $found->id);
    }

    public function test_all_payments(): void
    {
        $plan = Plan::factory()->create();
        $customer = Customer::factory()->create();
        $billing = Billing::factory()->create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
        ]);
        $creditCard = CreditCard::factory()->create(['customer_id' => $customer->id]);
        Payment::factory()->count(3)->create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
        ]);

        $payments = $this->service->all();

        $this->assertCount(3, $payments);
    }

    public function test_update_payment(): void
    {
        $plan = Plan::factory()->create();
        $customer = Customer::factory()->create();
        $billing = Billing::factory()->create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
        ]);
        $creditCard = CreditCard::factory()->create(['customer_id' => $customer->id]);
        $payment = Payment::factory()->create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
        ]);

        $updated = $this->service->update($payment->id, ['status' => PaymentStatus::REFUNDED]);

        $this->assertInstanceOf(Payment::class, $updated);
        $this->assertEquals(PaymentStatus::REFUNDED, $updated->status);
    }

    public function test_delete_payment(): void
    {
        $plan = Plan::factory()->create();
        $customer = Customer::factory()->create();
        $billing = Billing::factory()->create([
            'plan_id' => $plan->id,
            'customer_id' => $customer->id,
        ]);
        $creditCard = CreditCard::factory()->create(['customer_id' => $customer->id]);
        $payment = Payment::factory()->create([
            'billing_id' => $billing->id,
            'credit_card_id' => $creditCard->id,
        ]);

        $result = $this->service->delete($payment->id);

        $this->assertTrue($result);
        $this->assertNull(Payment::find($payment->id));
    }
}
