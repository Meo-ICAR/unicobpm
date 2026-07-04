<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_instances', function (Blueprint $table) {
            $table->id()->comment('ID univoco della pratica/istanza di processo');
            $table->unsignedBigInteger('process_id')->index()->comment('Quale processo si sta eseguendo (es. Onboarding)');

            // Il soggetto della pratica (es: l\'Agente Rossi)
            $table->morphs('subject');

            // Coda di lavoro: Chi ha preso in carico la pratica (Employer o Consultant)
            $table->nullableMorphs('current_assignee', 'assignee_index');

            // Multi-tenant (opzionale ma utile se hai più agenzie nel DB)
            $table->uuid('company_id')->nullable()->index()->comment('Agenzia di competenza');

            // Stato di avanzamento
            $table->enum('status', [
                'pending',      // Appena creata, in attesa di lavorazione
                'in_progress',  // In lavorazione
                'completed',    // Conclusa con successo
                'rejected',     // Respinta (KO)
                'cancelled',     // Annullata manualmente
            ])->default('pending')->comment('Stato globale della pratica');

            // Tracciamento avanzamento
            $table->unsignedBigInteger('current_task_id')->nullable()->comment('ID del task attualmente in lavorazione (per riprendere da dove si è lasciato)');
            $table->timestamp('completed_at')->nullable()->comment('Data di chiusura (positiva o negativa) della pratica');

            $table->timestamp('last_reminder_sent_at')->nullable()->comment('Data dell\'ultimo sollecito inviato');
            $table->integer('reminders_sent_count')->default(0)->comment('Quanti solleciti sono già stati inviati per il task attuale');

            $table->timestamps();

            // Vincoli e Chiavi
            $table->foreign('process_id')->references('id')->on('processes')->cascadeOnDelete();
            $table->foreign('current_task_id')->references('id')->on('process_tasks')->nullOnDelete();

            $table->comment('Le singole pratiche o istanze in esecuzione sui soggetti');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_instances');
    }
};
