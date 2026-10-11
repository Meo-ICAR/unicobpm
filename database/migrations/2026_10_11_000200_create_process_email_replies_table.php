<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Una riga per ogni risposta email collegata a un processo: cosa ha capito l'interprete, cosa si è fatto e chi ha deciso.
        Schema::create('process_email_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incoming_email_id')->unique()->comment('incoming_emails.id del database condiviso (nessun vincolo): una email si elabora una sola volta');
            $table->foreignId('process_instance_id')->constrained('process_instances')->cascadeOnDelete();
            $table->unsignedBigInteger('process_task_execution_id')->nullable();
            $table->unsignedBigInteger('process_task_item_id')->nullable()->comment('Azione «attesa risposta email» a cui risponde, se ce n\'è una in attesa');
            $table->string('from_email');
            $table->string('subject', 500)->nullable();
            $table->text('excerpt')->nullable()->comment('Inizio della risposta senza il testo citato (troncato)');
            $table->string('intent', 20)->nullable()->comment('confirm, refuse, postpone, question, other');
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('summary', 500)->nullable();
            $table->date('proposed_date')->nullable()->comment('Data proposta nella risposta (es. proroga)');
            $table->string('source', 10)->nullable()->comment('rule o ai: chi ha interpretato la risposta');
            $table->string('status', 20)->default('needs_review')->comment('applied (avanzato in automatico o da un operatore), needs_review, ignored, no_action');
            $table->foreignId('decided_by')->nullable()->comment('users.id di chi ha confermato o ignorato');
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('process_task_item_answer_id')->nullable()->comment('Risposta creata nel processo, se applicata');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_email_replies');
    }
};
