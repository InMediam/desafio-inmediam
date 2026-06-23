<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\CreditCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_fetch_a_billing(){

        $this->withoutExceptionHandling();
        
        // Criamos a cobrança usando a factory
        $billing = Billing::factory()->create(['status' => 'pending']);

        // Instanciamos a factory do cartão e pegamos apenas o array de dados brutos da Request
        $payload = CreditCard::factory()->requestPayload();

        dump($payload);

        // Fazemos a requisição simulando o envio do formulário frontend
        $response = $this->postJson("/api/billing/{$billing->id}/pay", $payload);

        $response->dump();

        // Asserts
        $response->assertStatus(200);
        $this->assertDatabaseHas('payments', [
            'billing_id' => $billing->id,
            'status' => 'CONFIRMED'
        ]);
    }
}
