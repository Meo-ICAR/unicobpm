<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->string('origin', 40)->nullable()->after('direction')->index()->comment('App che ha fatto la chiamata e ce l\'ha comunicata (es. unicoagent); nulla = chiamata di unicobpm stesso');
        });
    }

    public function down(): void
    {
        Schema::table('api_logs', fn (Blueprint $table) => $table->dropColumn('origin'));
    }
};
