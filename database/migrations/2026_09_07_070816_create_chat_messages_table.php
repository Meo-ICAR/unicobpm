<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store per-messaggio usato da NeuronAI EloquentChatHistory per l'assistente dati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->comment('Messaggi delle conversazioni con l\'assistente AI (NeuronAI)');

            $table->id();
            $table->string('thread_id')->index();
            $table->string('role');
            $table->json('content')->nullable();
            $table->json('meta')->nullable();

            // Chi ha generato il messaggio (per gli inbound dell'utente) o a nome di chi è
            // stata condotta la conversazione (per le risposte dell'assistente): nullable
            // perché una conversazione può non avere un proprietario (uso di sistema).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
