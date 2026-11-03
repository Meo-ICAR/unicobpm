<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('process_tasks') && ! Schema::hasColumn('process_tasks', 'is_active')) {
            Schema::table('process_tasks', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->comment('Se disattivo, il passo viene saltato nelle nuove pratiche');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('process_tasks', 'is_active')) {
            Schema::table('process_tasks', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
    }
};
