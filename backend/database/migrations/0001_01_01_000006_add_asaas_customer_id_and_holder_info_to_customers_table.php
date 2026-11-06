<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('asaas_customer_id')->nullable()->after('document');
            $table->string('phone')->nullable()->after('asaas_customer_id');
            $table->string('postal_code', 8)->nullable()->after('phone');
            $table->string('address_number')->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['asaas_customer_id', 'phone', 'postal_code', 'address_number']);
        });
    }
};
