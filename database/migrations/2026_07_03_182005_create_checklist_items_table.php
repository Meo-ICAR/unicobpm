<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id()->comment('ID univoco elemento checklist');
            $table->unsignedBigInteger('checklist_id')->index()->comment('Checklist di appartenenza');
            $table->string('ordine')->nullable()->comment('Ordine della domanda/elemento');
            $table->string('phase')->nullable()->comment('Fase della checklist');
            $table->boolean('is_phaseclose')->default(false)->comment('Se attività di chiusura della fase');
            $table->string('name')->nullable()->comment('Nome della domanda/elemento');
            $table->string('item_code')->nullable()->comment('Codice univoco della domanda');
            $table->text('question')->nullable()->comment('Testo della domanda');
            $table->text('answer')->nullable()->comment('Risposta data dall\'utente');
            $table->text('description')->nullable()->comment('Descrizione o note aggiuntive');
            $table->text('descriptioncheck')->nullable()->comment('Descrizione verifica conformita da effettuare');
            $table->text('annotation')->nullable()->comment('Annotazioni interne');
            $table->boolean('is_required')->default(false)->comment('Se obbligatorio');
            $table->enum('attach_model', ['principal', 'agent', 'company', 'audit'])->nullable()
                ->comment('Modello a cui allegare documento');
            $table->string('attach_model_id')->nullable()->comment('ID del modello per allegato');
            $table->integer('n_documents')->default(0)->comment('Numero documenti da allegare 0= no, 99=multi');
            $table->string('repeatable_code')->nullable()->comment('Codice se ripetibile (es. documenti annuali)');
            $table->string('document_type_codegroup')->nullable()->comment('Codice gruppo documenti');
            $table->string('document_type_code')->nullable()->comment('Codice gruppo documenti');
            $table->string('depends_on_code')->nullable()->comment('Il codice della domanda da cui dipende');
            $table->string('depends_on_value')->nullable()->comment('Il valore che deve avere per attivarsi');
            $table->enum('dependency_type', ['show_if', 'hide_if'])->nullable()->comment('Attiva / Disattiva condizionale');
            $table->string('url_step')->nullable()->comment('Link esterno per step procedure');
            $table->string('url_callback')->nullable()->comment('Link esterno per callback');
            $table->unsignedBigInteger('business_function_id')->nullable();
            $table->string('process_task_code')->index()->nullable();
            $table->unsignedBigInteger('process_task_id')->nullable();
            $table->boolean('is_completed')->default(false)->comment('Step completato');
            $table->string('target_model')->nullable()->comment('Modello del parent da ascoltare');
            $table->string('target_field')->nullable()->comment('Colonna del parent da ascoltare');
            $table->string('target_value')->nullable()->comment('Valore esatto che fa scattare la spunta');
            $table->boolean('is_timestamp_update')->default(false)->comment('Se true, basta che il target_field non sia null per spuntare');
            $table->timestamps();

            // Indici aggiuntivi
            $table->index(['attach_model', 'attach_model_id']);

            // Chiavi esterne (assumendo che esistano le tabelle relative)
            $table->foreign('business_function_id')->references('id')->on('business_functions')->nullOnDelete();
            $table->foreign('checklist_id')->references('id')->on('checklists')->cascadeOnDelete();
            $table->foreign('process_task_id')->references('id')->on('process_tasks')->nullOnDelete();

            $table->comment('Elementi delle checklist con domande e allegati');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
