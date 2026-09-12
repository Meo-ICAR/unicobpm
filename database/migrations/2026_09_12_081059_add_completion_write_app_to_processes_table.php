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
            // Quale applicativo esterno (vedi config('services.apps')) scrive
            // completion_write_field. Null => 'unicoloan' (default applicato dal codice).
            $table->string('completion_write_app')->nullable()->after('completion_write_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn('completion_write_app');
        });
    }
};
