<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->comment('Processi aziendali principali che rappresentano le attività strategiche e operative dell’organizzazione');

            $table->id()->comment('ID univoco del processo macro aziendale');
            $table->string('code')->unique()->comment('Codice identificativo univoco del processo (es. PRC-AML)');
            $table->string('name')->comment('Nome esplicativo del processo');
            $table->text('description')->nullable()->comment('Descrizione dettagliata delle finalità e dell’ambito del processo');
            $table->integer('version')->default(1)->comment('Incrementato se cambiano i task del processo');
            $table->boolean('is_active')->default(true)->comment('Stato di attivazione del processo (attivo/disattivato)');
            $table->string('target_model')->nullable();  // Modello
            $table->json('trigger_filters')->nullable(); // Filtri opzionali, Es. {"status": "active"}
            $table->string('trigger_field')->nullable()->comment('Campo del modello da controllare');
            $table->string('trigger_state')->nullable()->comment('filled, empty, equals');
            $table->string('trigger_value')->nullable()->comment('Il valore specifico da controllare');
            $table->string('exclude_field')->nullable()->comment('Campo del modello da escludere se valorizzato');
            $table->string('exclude_state')->nullable()->comment('filled, empty, equals');
            $table->string('exclude_value')->nullable()->comment('Il valore specifico da controllare');

            // --- CONFIGURAZIONE PERIODICITÀ (Trigger Automativi) ---
            $table->boolean('is_periodic')->default(false);
            $table->string('cron_expression')->nullable(); // Es. "0 1 10 * *" (Il 10 di ogni mese all'1:00)

            // --- TRACCIAMENTO TEMPORALE JOB ---
            $table->dateTime('last_activated_at')->nullable(); // Ultima esecuzione del processo
            $table->dateTime('next_run_at')->nullable();       // Prossima esecuzione calcolata o forzata

            $table->timestamps();

            $table->comment('Anagrafica dei macro processi aziendali (es. Adeguata Verifica, Trasparenza)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
