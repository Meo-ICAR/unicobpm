<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_tasks', function (Blueprint $table) {
            $table->id()->comment('ID univoco del task di processo');
            $table->unsignedBigInteger('process_id')->index()->comment('Riferimento al macro processo di appartenenza'); // <-- Chiave Esterna
            $table->string('code')->unique()->comment('Codice univoco interno del task');
            $table->string('name')->comment('Nome esplicativo del task');
            $table->text('description')->nullable()->comment('Descrizione dettagliata delle attività del task');
            $table->unsignedBigInteger('business_function_id')->nullable()->comment('Funzione di business associata');
            $table->timestamps();

            // Vincoli d'integrità referenziale
            $table->foreign('process_id')->references('id')->on('processes')->cascadeOnDelete();
            $table->foreign('business_function_id')->references('id')->on('business_functions')->nullOnDelete();

            $table->comment('Anagrafica dei task di processo aziendali collegati alle checklist');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_tasks');
    }
};
