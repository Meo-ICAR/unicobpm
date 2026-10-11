<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coda delle chiamate in uscita verso le altre app e verso i webhook di terzi: ogni chiamata ha uno stato e viene ritentata.
        Schema::create('outbound_calls', function (Blueprint $table) {
            $table->id();
            $table->string('event', 60)->comment('Cosa l\'ha generata (es. completion_write, webhook:process.status_changed)');
            $table->string('app', 40)->nullable()->comment('App esterna (chiave di services.apps); nulla per i webhook, che hanno un URL assoluto');
            $table->string('method', 10)->default('POST');
            $table->string('path', 500)->comment('Percorso sull\'app (es. /api/models/fornitore/123) oppure URL assoluto del webhook');
            $table->json('payload')->nullable();
            $table->json('meta')->nullable()->comment('Dati per il dopo: id dell\'istanza, campo scritto, endpoint del webhook');
            $table->string('idempotency_key', 80)->unique()->comment('Stessa chiave = stessa chiamata: non si accoda due volte');
            $table->string('correlation_id', 64)->nullable();
            $table->string('status', 12)->default('pending')->comment('pending, sent, failed');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url', 500)->comment('Indirizzo che riceve gli eventi (https)');
            $table->text('secret')->comment('Segreto con cui si firma ogni invio (HMAC-SHA256 nell\'intestazione X-Unico-Signature), cifrato');
            $table->json('events')->comment('Eventi a cui è iscritto; vuoto = tutti');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('outbound_calls');
    }
};
