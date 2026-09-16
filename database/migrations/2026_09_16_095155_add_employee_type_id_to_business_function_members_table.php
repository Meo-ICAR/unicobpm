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
            // employee_types.id è un int firmato (non unsignedBigInteger), quindi la
            // colonna qui deve avere lo stesso tipo per essere compatibile con la FK.
            $table->integer('employee_type_id')->nullable()->after('business_function_id');

            $table->foreign('employee_type_id')
                ->references('id')->on('employee_types')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_function_members', function (Blueprint $table) {
            $table->dropForeign(['employee_type_id']);
            $table->dropColumn('employee_type_id');
        });
    }
};
