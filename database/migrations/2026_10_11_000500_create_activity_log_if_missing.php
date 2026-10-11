<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Il registro attività è una tabella del pacchetto unico-core (database core). Quando il database di unicobpm è diverso da quello core
 * (sviluppo: unicobpm_dev + unicooam_core) le migration del pacchetto non girano qui, ma i modelli del processo scrivono sulla connessione
 * predefinita: senza questa tabella ogni activity() falliva (es. l'avvio di un processo). Stessa struttura di quella del pacchetto; se c'è già, non fa nulla.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_log')) {
            return;
        }

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);
            $table->string('event')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->index(['causer_type', 'causer_id']);
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->string('risk_level', 16)->nullable();
            $table->boolean('retention_hold')->default(false);
            $table->char('integrity_hash', 64)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['log_name'], 'activity_log_log_name_index');
            $table->index(['log_name', 'created_at'], 'activity_log_log_created_index');
        });
    }

    public function down(): void
    {
        // Non si elimina: potrebbe essere la tabella del pacchetto.
    }
};
