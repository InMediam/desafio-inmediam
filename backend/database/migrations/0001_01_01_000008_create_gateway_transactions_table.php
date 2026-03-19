<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * History of attempts (success or failure) of transactions with gateways.
     * Allows tracking each payment attempt per billing/customer.
     */
    public function up(): void
    {
        Schema::create('gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billings')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('external_id', 191)->nullable()->comment('Charge/payment ID in the gateway');
            $table->foreignId('credit_card_id')->nullable()->constrained('credit_cards')->nullOnDelete();
            $table->string('card_display', 50)->nullable()->comment('E.g. ****1234');
            $table->json('payload')->nullable()->comment('Payload sent to the gateway');
            $table->json('response')->nullable()->comment('Response received from the gateway');
            $table->text('message')->nullable()->comment('Success or failure message');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status', 20)->comment('success, failed, pending');
            $table->string('gateway', 30)->comment('asaas, stripe, etc.');
            $table->timestamps();

            $table->index('billing_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('gateway');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_transactions');
    }
};
