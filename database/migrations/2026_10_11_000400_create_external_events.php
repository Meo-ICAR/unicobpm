<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Eventi che le altre app (o terzi) comunicano a unicobpm: ognuno si registra una sola volta e può avviare processi.
        if (! Schema::hasTable('external_events')) {
            Schema::create('external_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 120)->unique()->comment('Identificativo dell\'evento dato dal mittente: lo stesso evento inviato due volte vale una volta');
                $table->string('name', 80)->comment('Nome dell\'evento (es. pratica.perfezionata)');
                $table->string('source', 40)->nullable()->comment('App o sistema che l\'ha inviato');
                $table->string('subject_ref', 150)->nullable()->comment('Riferimento dell\'oggetto dell\'evento nell\'app di origine (es. codice pratica)');
                $table->json('data')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->string('status', 12)->default('received')->comment('received, processed (ha avviato almeno un processo), ignored (nessun trigger applicabile), failed');
                $table->unsignedSmallInteger('started_count')->default(0);
                $table->timestamps();
                $table->index(['name', 'status']);
            });
        }

        Schema::table('process_triggers', function (Blueprint $table) {
            if (Schema::hasColumn('process_triggers', 'external_event')) {
                return;
            }

            $table->string('model_class')->nullable()->change();
            $table->string('external_event', 80)->nullable()->after('event_type')->comment('Nome dell\'evento esterno che avvia il processo (event_type = external)');
        });

        Schema::table('process_instances', function (Blueprint $table) {
            if (Schema::hasColumn('process_instances', 'external_ref')) {
                return;
            }

            $table->string('external_ref', 150)->nullable()->after('title')->index()->comment('Riferimento nell\'app di origine (es. unicoagent:FIN-2026-0300)');
            $table->json('external_data')->nullable()->after('external_ref')->comment('Dati dell\'evento che ha avviato il processo');
        });
    }

    public function down(): void
    {
        Schema::table('process_instances', fn (Blueprint $table) => $table->dropColumn(['external_ref', 'external_data']));
        Schema::table('process_triggers', fn (Blueprint $table) => $table->dropColumn('external_event'));
        Schema::dropIfExists('external_events');
    }
};
