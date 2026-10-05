<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_task_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_instance_id')->constrained('process_instances')->cascadeOnDelete();
            $table->foreignId('process_task_id')->constrained('process_tasks')->cascadeOnDelete();

            // Tracciamento polimorfico di chi ha eseguito la lavorazione
            $table->nullableMorphs('assignee');

            $table->timestamp('started_at')->comment('Quando il task è entrato in coda per l\'ufficio');
            $table->timestamp('claimed_at')->nullable()->comment('Quando l\'operatore ha preso in carico il task');
            $table->timestamp('completed_at')->nullable()->comment('Quando il task è stato chiuso (successo o rifiuto)');
            $table->integer('escalation_level')->default(0)->comment('Livello di escalation del task');

            // (Opzionale) La data entro cui il task andava chiuso
            $table->dateTime('due_at')->nullable()->comment('Data entro cui il task andava chiuso');

            $table->string('execution_status')->default('completed')->comment('completed, rejected_and_rewinded');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_task_executions');
    }
};
