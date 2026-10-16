<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('origin')->index()
                ->comment('Agenzia (companies.id, database condiviso), se la chiamata la indica');
        });
    }

    public function down(): void
    {
        Schema::table('api_logs', fn (Blueprint $table) => $table->dropColumn('company_id'));
    }
};
