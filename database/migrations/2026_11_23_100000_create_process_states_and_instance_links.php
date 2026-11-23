<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stati applicativi dei processi (es. i 21 stati del reclutamento della rete: «Candidatura ricevuta», «LOI in firma»...) e due campi
 * nelle pratiche (`process_instances`):
 *  - `process_state_id`: lo stato applicativo corrente della pratica;
 *  - `incoming_email_id`: l'email, letta da unicopec (`incoming_emails`), da cui nasce la pratica (es. la candidatura).
 * Protetta da hasTable/hasColumn: si può rieseguire.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('process_states')) {
            Schema::create('process_states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();
                $table->foreignId('process_task_id')->nullable()->constrained('process_tasks')->nullOnDelete()->comment('Fase (passo) a cui lo stato appartiene');
                $table->string('code', 10)->comment('Codice dello stato (es. 07)');
                $table->string('name');
                $table->string('owner')->nullable()->comment('Funzioni responsabili dello stato (es. COM/SEG)');
                $table->unsignedSmallInteger('ordine')->default(0);
                $table->boolean('is_final')->default(false)->comment('Stato conclusivo: non si cambia in automatico');
                $table->boolean('is_exception')->default(false)->comment('Stato di eccezione (es. sospeso): non si cambia in automatico');
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['process_id', 'code']);
            });
        }

        if (! Schema::hasColumn('process_instances', 'process_state_id')) {
            Schema::table('process_instances', function (Blueprint $table) {
                $table->foreignId('process_state_id')->nullable()->after('status')->constrained('process_states')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('process_instances', 'incoming_email_id')) {
            Schema::table('process_instances', function (Blueprint $table) {
                $table->unsignedBigInteger('incoming_email_id')->nullable()->after('external_ref')->index()->comment('Email letta da unicopec da cui nasce la pratica');
            });

            // Il riferimento alla tabella delle email di unicopec (stesso database) è una chiave esterna dove la tabella esiste.
            if (Schema::hasTable('incoming_emails')) {
                Schema::table('process_instances', function (Blueprint $table) {
                    $table->foreign('incoming_email_id')->references('id')->on('incoming_emails')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('process_instances', 'incoming_email_id')) {
            Schema::table('process_instances', function (Blueprint $table) {
                if (Schema::hasTable('incoming_emails')) {
                    $table->dropForeign(['incoming_email_id']);
                }
                $table->dropColumn('incoming_email_id');
            });
        }

        if (Schema::hasColumn('process_instances', 'process_state_id')) {
            Schema::table('process_instances', function (Blueprint $table) {
                $table->dropConstrainedForeignId('process_state_id');
            });
        }

        Schema::dropIfExists('process_states');
    }
};
