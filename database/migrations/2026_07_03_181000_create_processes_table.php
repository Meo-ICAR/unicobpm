<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processes', function (Blueprint $table) {
            $table->id()->comment('ID univoco del processo macro aziendale');
            $table->string('code')->unique()->comment('Codice identificativo univoco del processo (es. PRC-AML)');
            $table->string('name')->comment('Nome esplicativo del processo');
            $table->text('description')->nullable()->comment('Descrizione dettagliata delle finalità e dell’ambito del processo');
            $table->boolean('is_active')->default(true)->comment('Stato di attivazione del processo (attivo/disattivato)');
            $table->timestamps();

            $table->comment('Anagrafica dei macro processi aziendali (es. Adeguata Verifica, Trasparenza)');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
