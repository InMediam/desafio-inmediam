<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Adiciona o campo como string, permitindo nulo (pois clientes antigos não terão de início)
            // Criamos um índice (index) para otimizar futuras buscas por esse ID do gateway
            $table->string('asaas_id')->nullable()->after('document')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Remove a coluna caso seja necessário dar um rollback na migration
            $table->dropColumn('asaas_id');
        });
    }
};