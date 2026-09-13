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
        Schema::table('processes', function (Blueprint $table) {
            if (! Schema::hasColumn('processes', 'cron_expression')) {
                // Espressione cron opzionale: valutata periodicamente per scatenare il processo quando le
                // condizioni configurate (trigger_field/trigger_state/trigger_value, trigger_filters) sono
                // soddisfatte, indipendentemente dalla ricorrenza a calendario (recurrence_frequency/recurrence_day).
                $table->string('cron_expression')->nullable()->after('is_periodic');
            }
        });

        Schema::table('process_instances', function (Blueprint $table) {
            if (! Schema::hasColumn('process_instances', 'title')) {
                // Etichetta descrittiva per le pratiche senza un soggetto polimorfo specifico
                // (processi interni/ricorrenti avviati da ExecutePeriodicProcessJob/DataAnomalyWatchdogJob).
                $table->string('title')->nullable()->after('subject_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            if (Schema::hasColumn('processes', 'cron_expression')) {
                $table->dropColumn('cron_expression');
            }
        });

        Schema::table('process_instances', function (Blueprint $table) {
            if (Schema::hasColumn('process_instances', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};
