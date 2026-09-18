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
        Schema::table('process_task_item_answers', function (Blueprint $table) {
            // Chi/cosa ha davvero prodotto la risposta: un operatore umano, un bot procedurale
            // (regole/regex del motore BPM) o un agente AI. Complementa la convenzione esistente
            // "user_id = 0" senza sostituirla, per non rompere codice che già la usa.
            $table->enum('operator_type', ['human', 'procedural', 'ai'])->nullable()->after('user_id');
            $table->string('operator_label')->nullable()->after('operator_type')
                ->comment('Es: validation_rule, email_ingestion.regex, email_ingestion.ai, assistant.send_email');
            $table->unsignedTinyInteger('confidence')->nullable()->after('operator_label')
                ->comment('Confidenza 0-100 quando la risposta viene da un operatore AI');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_task_item_answers', function (Blueprint $table) {
            $table->dropColumn(['operator_type', 'operator_label', 'confidence']);
        });
    }
};
