<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('process_task_items') && ! Schema::hasColumn('process_task_items', 'is_active')) {
            Schema::table('process_task_items', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->comment('Se disattiva, l\'azione automatica non parte (la pratica la registra come saltata)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('process_task_items', 'is_active')) {
            Schema::table('process_task_items', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
