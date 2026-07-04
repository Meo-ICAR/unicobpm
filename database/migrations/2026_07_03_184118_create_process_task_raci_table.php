<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_task_raci', function (Blueprint $table) {
            $table->id()->comment('ID univoco assegnazione RACI');
            $table->unsignedBigInteger('process_task_id')->index()->comment('Task di processo di riferimento');
            $table->unsignedBigInteger('business_function_id')->index()->comment('Funzione di business coinvolta');
            $table->enum('raci_role', ['R', 'A', 'C', 'I'])->comment('Ruolo nella matrice: R(Responsible), A(Accountable), C(Consulted), I(Informed)');
            $table->text('notes')->nullable()->comment('Note specifiche sull\'assegnazione del ruolo per questo task');

            $table->timestamps();

            // Vincoli di unicità (una funzione ha un solo ruolo specifico per un determinato task)
            $table->unique(['process_task_id', 'business_function_id'], 'unique_raci_assignment');

            // Chiavi esterne
            $table->foreign('process_task_id')->references('id')->on('process_tasks')->cascadeOnDelete();
            $table->foreign('business_function_id')->references('id')->on('business_functions')->cascadeOnDelete();

            $table->comment('Matrice RACI: Mappatura delle responsabilità delle funzioni aziendali sui task di processo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_task_raci');
    }
};
