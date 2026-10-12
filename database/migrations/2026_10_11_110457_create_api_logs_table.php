<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 3)->comment('in = ricevuta da un\'altra app, out = inviata a un\'altra app o a un webhook');
            $table->string('app', 40)->nullable()->comment('App con cui si parla (mittente o destinataria)');
            $table->string('method', 10);
            $table->string('url', 500);
            $table->json('request_headers')->nullable()->comment('Senza segreti: chiavi, firme e token sono oscurati');
            $table->longText('request_body')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error', 200)->nullable()->comment('Tipo di errore di rete, se non c\'è stata risposta');
            $table->string('correlation_id', 64)->nullable()->index();
            $table->string('event_id', 120)->nullable()->index()->comment('event_id dell\'evento esterno, se la chiamata lo porta');
            $table->string('ai_model', 60)->nullable();
            $table->unsignedInteger('ai_input_tokens')->nullable();
            $table->unsignedInteger('ai_output_tokens')->nullable();
            $table->decimal('ai_cost', 10, 6)->nullable()->comment('Costo in USD dell\'interrogazione AI che ha prodotto i dati della chiamata');
            $table->timestamps();
            $table->index(['direction', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
