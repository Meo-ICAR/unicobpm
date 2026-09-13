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
        Schema::table('process_task_items', function (Blueprint $table) {
            // Checklist da compilare quando action_type = 'fill_checklist'. "checklists" vive sulla
            // stessa connessione locale del motore BPM, quindi qui la FK è un vincolo vero.
            $table->foreignId('checklist_id')
                ->nullable()
                ->after('document_type_id')
                ->constrained('checklists')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_task_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_id');
        });
    }
};
