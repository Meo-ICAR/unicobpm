<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('checklist_id')->index()->comment('Modulo/Checklist di appartenenza');

            // UI e Contenuto della domanda
            $table->string('item_code')->unique()->comment('Codice univoco della domanda (utile per API o integrazioni)');
            $table->integer('ordine')->default(0)->comment('Ordine di apparizione nel form');
            $table->string('name')->comment('Label breve o nome della domanda');
            $table->text('question')->nullable()->comment('Testo esteso della domanda o istruzione esplicativa');
            $table->enum('type', ['boolean', 'text', 'number', 'date', 'select', 'multiselect'])->default('boolean')->comment('Tipo di input HTML da generare');
            $table->text('options')->nullable()->comment('JSON con le opzioni se type è select o multiselect');
            $table->boolean('is_required')->default(true);

            // --- AUTOMAZIONI DATA-DRIVEN ---
            // Se questi campi sono popolati, l'Observer aggiorna l'anagrafica in automatico
            $table->string('trigger_field')->nullable()->comment('Campo del modello da controllare');
            $table->string('trigger_state')->nullable()->comment('filled, empty, equals');
            $table->string('trigger_value')->nullable()->comment('Il valore specifico da controllare');
            $table->string('exclude_field')->nullable()->comment('Campo del modello da escludere se valorizzato');
            $table->string('exclude_state')->nullable()->comment('filled, empty, equals');
            $table->string('exclude_value')->nullable()->comment('Il valore specifico da controllare');
            $table->boolean('is_timestamp_update')->default(false)->comment('Se true, inserisce in automatico il Carbon::now() nella colonna target');

            // --- REGOLE DI KNOCKOUT (KO) ---
            $table->boolean('is_knockout')->default(false)->comment('Se true, valuta la risposta per un potenziale respingimento istantaneo');
            $table->string('knockout_value')->nullable()->comment('Se la risposta utente combacia con questo valore, la pratica passa in status "rejected"');

            // Logica di visibilità intra-checklist (opzionale, se una domanda dipende da un'altra)
            $table->string('depends_on_code')->nullable()->comment('item_code della domanda da cui dipende');
            $table->string('depends_on_value')->nullable()->comment('Mostra solo se la domanda madre ha questo valore');

            $table->timestamps();

            $table->foreign('checklist_id')->references('id')->on('checklists')->cascadeOnDelete();

            $table->comment('I campi dei moduli con regole di automazione stato e KO');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
