<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_task_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('process_task_id')->index();

            // Dati dell'azione
            $table->string('name')->comment('Nome dell\'azione (es. Compila Questionario, Carica Visura)');
            $table->integer('ordine')->default(0)->comment('Ordine di visualizzazione all\'interno del task');

            // Il "Router" dell'azione: dice al frontend cosa renderizzare a schermo
            $table->enum('action_type', [
                'document_upload', // Mostra un dropzone per i file
                'fill_checklist',  // Mostra un modulo di domande
                'approval_toggle', // Mostra uno switch di approvazione semplice
                'system_task',     // Mostra un task di sistema
                'text_input',       // Mostra un campo di testo libero
            ])->comment('Tipologia di UI e logica da caricare');

            // Riferimenti opzionali in base all'action_type
            $table->string('document_type_code')->nullable()->comment('Popolato se action_type = document_upload (es. VIS_CAM)');
            $table->unsignedBigInteger('checklist_id')->nullable()->comment('Popolato se action_type = fill_checklist');

            $table->boolean('is_required')->default(true)->comment('Se false, il task può essere chiuso anche senza questa azione');
            $table->string('handler_job')->nullable()->comment('Classe Job Laravel, es: App\Jobs\AIVerifyDocumentJob');

            $table->timestamps();

            // Vincoli di integrità
            $table->foreign('process_task_id')->references('id')->on('process_tasks')->cascadeOnDelete();
            $table->foreign('checklist_id')->references('id')->on('checklists')->nullOnDelete();

            $table->comment('Le singole azioni (upload, form, flag) richieste dentro un Task');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_task_items');
    }
};
