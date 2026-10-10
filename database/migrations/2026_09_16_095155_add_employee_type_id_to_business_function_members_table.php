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
        Schema::table('business_function_members', function (Blueprint $table) {
            // Ruolo (EmployeeType) ricoperto dal member in questa funzione aziendale.
            // Il member (Employee o Client) resta invariato: un Client qui rappresenta
            // il ruolo esternalizzato su un consulente.
            // employee_types è del pacchetto unico-core (database condiviso): niente vincolo, solo l'indice.
            $table->unsignedBigInteger('employee_type_id')->nullable()->index()->after('business_function_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_function_members', function (Blueprint $table) {
            $table->dropColumn('employee_type_id');
        });
    }
};
