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
            $table->string('processable')->nullable();  // Modello
            $table->string('trigger_field')->nullable()->comment('Campo del modello da controllare');
            $table->string('trigger_state')->nullable()->comment('filled, empty, equals');
            $table->string('trigger_value')->nullable()->comment('Il valore specifico da controllare');
            $table->string('exclude_field')->nullable()->comment('Campo del modello da escludere se valorizzato');
            $table->string('exclude_state')->nullable()->comment('filled, empty, equals');
            $table->string('exclude_value')->nullable()->comment('Il valore specifico da controllare');

            $table->timestamps();

            $table->comment('Anagrafica dei macro processi aziendali (es. Adeguata Verifica, Trasparenza)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
