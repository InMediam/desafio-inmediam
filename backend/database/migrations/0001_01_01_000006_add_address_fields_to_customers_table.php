<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 11)->nullable()->after('document');
            $table->string('postal_code', 8)->nullable()->after('phone');
            $table->string('address_number', 10)->nullable()->after('postal_code');
        });

        DB::table('customers')->update([
            'phone' => '11999999999',
            'postal_code' => '01310100',
            'address_number' => '100',
        ]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['phone', 'postal_code', 'address_number']);
        });
    }
};
