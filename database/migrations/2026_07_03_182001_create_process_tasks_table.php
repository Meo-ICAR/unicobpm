<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_tasks', function (Blueprint $table) {
            $table->comment('Fasi dei Processi aziendali');

            $table->id()->comment('ID univoco del task di processo');
            $table->unsignedBigInteger('process_id')->index()->comment('Riferimento al macro processo di appartenenza'); // <-- Chiave Esterna
            $table->string('code')->nullable()->comment('Codice univoco interno del task');
            $table->string('name')->nullable()->comment('Nome esplicativo del task');
            $table->text('description')->nullable()->comment('Descrizione dettagliata delle attività del task');

            $table->integer('ordine')->default(0)->comment('Ordine sequenziale di esecuzione nel processo');

            $table->unsignedBigInteger('business_function_id')->nullable()->comment('Funzione di business associata');

            $table->string('trigger_field')->nullable()->comment('Campo del modello da controllare');
            $table->string('trigger_state')->nullable()->comment('filled, empty, equals');
            $table->string('trigger_value')->nullable()->comment('Il valore specifico da controllare');
            $table->string('exclude_field')->nullable()->comment('Campo del modello da escludere se valorizzato');
            $table->string('exclude_state')->nullable()->comment('filled, empty, equals');
            $table->string('exclude_value')->nullable()->comment('Il valore specifico da controllare');

            // Timer Boundary (Solleciti automatici)
            $table->boolean('has_reminders')->default(false);
            $table->integer('reminder_interval_days')->default(3);
            $table->integer('max_reminders')->default(5);

            // --- SLA & ESCALATION ---
            // JSON contenente i livelli di sollecito e i tempi massimi
            $table->json('escalation_rules')->nullable();

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
