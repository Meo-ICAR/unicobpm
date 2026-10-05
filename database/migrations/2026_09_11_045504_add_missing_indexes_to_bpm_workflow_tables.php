<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('process_instances', function (Blueprint $table) {
            $table->index('status');
            $table->index('current_task_id');
        });

        // Il motore BPM assegna anche lo stato 'suspended' (pratiche bloccate da una validazione fallita),
        // che non era previsto nell'enum originale. L'ALTER ENUM è sintassi MySQL/MariaDB;
        // sqlite (usato nei test) non ha un vero tipo enum e non richiede questa modifica.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE process_instances MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'rejected', 'cancelled', 'suspended') NOT NULL DEFAULT 'pending' COMMENT 'Stato globale della pratica'");
        }

        Schema::table('process_task_executions', function (Blueprint $table) {
            $table->index('execution_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_instances', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['current_task_id']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE process_instances MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Stato globale della pratica'");
        }

        Schema::table('process_task_executions', function (Blueprint $table) {
            $table->dropIndex(['execution_status']);
        });
    }
};
