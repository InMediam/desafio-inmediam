<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('postal_code', 9)->nullable()->after('document');
            $table->string('street')->nullable()->after('postal_code');
            $table->string('address_number', 20)->nullable()->after('street');
            $table->string('address_complement')->nullable()->after('address_number');
            $table->string('neighborhood')->nullable()->after('address_complement');
            $table->string('city')->nullable()->after('neighborhood');
            $table->string('state', 2)->nullable()->after('city');
            $table->string('phone', 20)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'postal_code',
                'street',
                'address_number',
                'address_complement',
                'neighborhood',
                'city',
                'state',
                'phone',
            ]);
        });
    }
};
