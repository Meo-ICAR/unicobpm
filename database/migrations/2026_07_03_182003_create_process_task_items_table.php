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
        Schema::create('process_task_items', function (Blueprint $blueprint) {
            $blueprint->id();
            // Chiave esterna verso il task padre
            $blueprint->foreignId('process_task_id')
                ->constrained('process_tasks')
                ->cascadeOnDelete();

            $blueprint->string('name');
            $blueprint->integer('ordine')->default(0);

            // Tipi azione: 'document_upload', 'fill_checklist', 'text_input', 'system_task', 'external_url', 'custom_email', ecc.
            $blueprint->string('action_type');
            $blueprint->boolean('is_required')->default(true);

            // Relazione opzionale: valorizzata solo se action_type è 'document_upload'
            $blueprint->foreignId('document_type_id')
                ->nullable()
                ->constrained('document_types')
                ->nullOnDelete();

            // Stringa opzionale per i system_task (Job Laravel da lanciare)
            $blueprint->string('handler_job')->nullable();

            // Campo JSON fondamentale: ospita la configurazione di URL, template email, ecc.
            $blueprint->json('config')->nullable();

            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_task_items');
    }
};
