<?php

namespace Tests\Unit\Services\Billing;

use App\Exceptions\BillingAlreadyPaidException;
use App\Exceptions\PaymentDeclinedException;
use App\Models\Billing;
use App\Services\Billing\BillingPaymentService;
use App\Services\Billing\CreditCardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Tests\Support\FakePaymentGateway;
use Tests\TestCase;

class BillingPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_charge_marks_the_billing_as_paid(): void
    {
        $gateway = new FakePaymentGateway;
        $service = new BillingPaymentService($gateway);

        $billing = Billing::factory()->pending()->create();

        $payment = $service->pay((string) $billing->id, $this->card(), null);

        $this->assertSame('paid', $billing->refresh()->status);
        $this->assertSame('CONFIRMED', $payment->status);
    }

    public function test_a_declined_charge_keeps_the_billing_pending(): void
    {
        $gateway = new FakePaymentGateway(chargeResult: [
            'httpStatus' => 400,
            'status' => null,
            'cardLastFour' => null,
            'cardBrand' => null,
            'cardToken' => null,
            'errorCode' => 'invalid_creditCard',
            'errorDescription' => 'Cartão recusado pela operadora.',
        ]);
        $service = new BillingPaymentService($gateway);

        $billing = Billing::factory()->pending()->create();

        try {
            $service->pay((string) $billing->id, $this->card(), null);
            $this->fail('Expected a PaymentDeclinedException to be thrown.');
        } catch (PaymentDeclinedException) {
        }

        $this->assertSame('pending', $billing->refresh()->status);
    }

    public function test_paying_an_already_paid_billing_throws_without_calling_the_gateway(): void
    {
        $gateway = new FakePaymentGateway;
        $service = new BillingPaymentService($gateway);

        $billing = Billing::factory()->paid()->create();

        try {
            $service->pay((string) $billing->id, $this->card(), null);
            $this->fail('Expected a BillingAlreadyPaidException to be thrown.');
        } catch (BillingAlreadyPaidException) {
        }

        $this->assertSame(0, $gateway->payWithCreditCardCalls);
    }

    public function test_no_log_event_contains_the_card_number_or_cvv(): void
    {
        $handler = new TestHandler;

        Log::extend('capturing_test_handler', fn () => new Monolog('payments', [$handler]));
        config(['logging.channels.payments' => ['driver' => 'capturing_test_handler']]);

        $gateway = new FakePaymentGateway;
        $service = new BillingPaymentService($gateway);
        $billing = Billing::factory()->pending()->create();
        $card = $this->card();

        $service->pay((string) $billing->id, $card, null);

        $this->assertNotEmpty($handler->getRecords());

        foreach ($handler->getRecords() as $record) {
            $payload = $record->message.json_encode($record->context);

            $this->assertStringNotContainsString($card->number, $payload);
            $this->assertStringNotContainsString($card->cvv, $payload);
        }
    }

    private function card(): CreditCardData
    {
        return new CreditCardData(
            holderName: 'JOAO DA SILVA',
            number: '4539656048392220',
            expiryMonth: '01',
            expiryYear: '2030',
            cvv: '123',
        );
    }
}
