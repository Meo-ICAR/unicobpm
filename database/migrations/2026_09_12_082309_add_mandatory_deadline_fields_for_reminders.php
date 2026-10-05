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
        Schema::table('process_instances', function (Blueprint $table) {
            // Data tassativa di termine dell'intera istanza di processo (facoltativa).
            $table->date('hard_deadline_at')->nullable()->after('completed_at');
        });

        Schema::table('process_task_executions', function (Blueprint $table) {
            // Giorni entro cui questo specifico step deve essere tassativamente completato
            // (facoltativo, sovrascrive/affianca il default ereditato dal ProcessTask template).
            $table->unsignedSmallInteger('mandatory_days_to_complete')->nullable()->after('due_at');
        });

        Schema::table('processes', function (Blueprint $table) {
            // Se true, il conteggio dei record che oggi soddisfano i criteri di attivazione
            // del processo (target_model + trigger/exclude) viene incluso nei reminder
            // inviati all'utente responsabile (RACI 'R') dei task aperti.
            $table->boolean('include_eligible_count_in_reminders')->default(false)->after('completion_write_app');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_instances', function (Blueprint $table) {
            $table->dropColumn('hard_deadline_at');
        });

        Schema::table('process_task_executions', function (Blueprint $table) {
            $table->dropColumn('mandatory_days_to_complete');
        });

        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn('include_eligible_count_in_reminders');
        });
    }
};
