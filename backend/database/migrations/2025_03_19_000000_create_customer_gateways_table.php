<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('gateway', 64);
            $table->string('external_id');
            $table->timestamps();

            $table->unique(['customer_id', 'gateway']);
        });

        if (Schema::hasColumn('customers', 'asaas_customer_id')) {
            $rows = DB::table('customers')
                ->whereNotNull('asaas_customer_id')
                ->where('asaas_customer_id', '!=', '')
                ->select('id', 'asaas_customer_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('customer_gateways')->insert([
                    'customer_id' => $row->id,
                    'gateway' => 'asaas',
                    'external_id' => $row->asaas_customer_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('asaas_customer_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('customers', 'asaas_customer_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('asaas_customer_id')->nullable()->after('document');
            });
        }

        $rows = DB::table('customer_gateways')->where('gateway', 'asaas')->get();
        foreach ($rows as $row) {
            DB::table('customers')->where('id', $row->customer_id)->update([
                'asaas_customer_id' => $row->external_id,
            ]);
        }

        Schema::dropIfExists('customer_gateways');
    }
};
