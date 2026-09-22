<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropForeign(['customer_id']);

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->restrictOnDelete();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();
        });

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['billing_id']);
            $table->dropForeign(['credit_card_id']);

            $table->foreign('billing_id')
                ->references('id')
                ->on('billings')
                ->restrictOnDelete();

            $table->foreign('credit_card_id')
                ->references('id')
                ->on('credit_cards')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropForeign(['customer_id']);

            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->cascadeOnDelete();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();
        });

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['billing_id']);
            $table->dropForeign(['credit_card_id']);

            $table->foreign('billing_id')
                ->references('id')
                ->on('billings')
                ->cascadeOnDelete();

            $table->foreign('credit_card_id')
                ->references('id')
                ->on('credit_cards')
                ->cascadeOnDelete();
        });
    }
};
