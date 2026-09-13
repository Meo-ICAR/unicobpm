<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collega la gerarchia reale del motore BPM:
     *
     *   ProcessInstance -> ProcessTaskExecution -> ProcessTaskItemAnswer -> ChecklistSubmission -> ChecklistAnswer
     *
     * Un'esecuzione di task (ProcessTaskExecution) ha le sue risposte alle azioni del task
     * (ProcessTaskItemAnswer, una per ogni ProcessTaskItem: upload, testo, ecc.). Quando
     * l'azione è "fill_checklist", quella specifica risposta è collegata a una ChecklistSubmission
     * (la compilazione della checklist), che a sua volta ha le sue ChecklistAnswer, una per
     * ogni ChecklistItem. Le colonne process_instance_id esistenti su process_task_item_answers
     * e checklist_answers restano, come scorciatoia denormalizzata per le query già esistenti
     * (es. ProcessInstance::taskItemAnswers()); il collegamento "canonico" è però quello sopra.
     */
    public function up(): void
    {
        Schema::table('process_task_item_answers', function (Blueprint $table) {
            $table->foreignId('process_task_execution_id')
                ->nullable()
                ->after('process_instance_id')
                ->constrained('process_task_executions')
                ->cascadeOnDelete();
        });

        Schema::table('checklist_submissions', function (Blueprint $table) {
            $table->foreignId('process_task_item_answer_id')
                ->nullable()
                ->after('checklist_id')
                ->constrained('process_task_item_answers')
                ->cascadeOnDelete();
        });

        Schema::table('checklist_answers', function (Blueprint $table) {
            $table->foreignId('checklist_submission_id')
                ->nullable()
                ->after('process_instance_id')
                ->constrained('checklist_submissions')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_task_item_answers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('process_task_execution_id');
        });

        Schema::table('checklist_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('process_task_item_answer_id');
        });

        Schema::table('checklist_answers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_submission_id');
        });
    }
};
