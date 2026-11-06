<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\PayBillingRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PayBillingRequestTest extends TestCase
{
    public function test_it_converts_the_mm_yy_expiry_date_into_month_and_four_digit_year(): void
    {
        $request = PayBillingRequest::create('/billing/1/pay', 'POST', ['expiry_date' => '01/30']);

        $this->assertSame('01', $request->expiryMonth());
        $this->assertSame('2030', $request->expiryYear());
    }

    public function test_it_fails_validation_for_an_expired_card(): void
    {
        $request = new PayBillingRequest;

        $validator = Validator::make([
            'card_number' => '4539656048392220',
            'card_holder_name' => 'JOAO DA SILVA',
            'expiry_date' => now()->subYear()->format('m/y'),
            'cvv' => '123',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('vencido', $validator->errors()->first('expiry_date'));
    }
}
