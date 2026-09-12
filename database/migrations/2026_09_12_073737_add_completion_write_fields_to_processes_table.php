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
            // Al completamento dell'istanza, se valorizzato, il campo del soggetto da scrivere
            // (es. 'stipulated_at' per l'Onboarding, 'dismissed_at' per l'Offboarding).
            $table->string('completion_write_field')->nullable()->after('exclude_value');
            // Valore da scrivere: il sentinel 'now' viene risolto alla data/ora corrente al momento
            // della scrittura; qualunque altro valore è scritto letteralmente.
            $table->string('completion_write_value')->nullable()->after('completion_write_field');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn(['completion_write_field', 'completion_write_value']);
        });
    }
};
