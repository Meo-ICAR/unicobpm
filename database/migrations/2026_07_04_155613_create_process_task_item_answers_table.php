<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_task_item_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_instance_id')->constrained('process_instances')->cascadeOnDelete();
            $table->foreignId('process_task_item_id')->constrained('process_task_items')->cascadeOnDelete();

            // DMS Link: se l\'azione è un upload, punta al file salvato centralmente
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            // Valori generici di risposta
            $table->boolean('value_boolean')->nullable();
            $table->text('value_text')->nullable();

            // Chi ha eseguito l\'azione (ID utente o 0 per i sistemi automatici/AI)
            $table->unsignedBigInteger('user_id')->nullable()->comment('0 = Sistema/AI');

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_task_item_answers');
    }
};
