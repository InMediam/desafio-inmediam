<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->string('asaas_charge_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropColumn('asaas_charge_id');
        });
    }
};
