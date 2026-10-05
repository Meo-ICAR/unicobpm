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
        Schema::create('ai_action_drafts', function (Blueprint $table) {
            $table->id();

            // Tipo di azione preparata dall'assistente AI (per ora solo 'send_email', pensato
            // per crescere con altre azioni confermabili dall'operatore in futuro).
            $table->string('type')->default('send_email');

            // Dati dell'azione proposta (destinatario, oggetto, corpo, ecc.), specifici per 'type'.
            $table->json('payload');

            $table->enum('status', ['pending', 'sent', 'cancelled', 'failed'])->default('pending');

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_action_drafts');
    }
};
