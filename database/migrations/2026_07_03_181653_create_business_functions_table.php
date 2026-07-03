<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_functions', function (Blueprint $table) {
            $table->id()->comment('ID univoco funzione business');
            $table->string('code')->unique()->comment('Codice identificativo univoco funzione');
            $table->enum('macro_area', [
                'Governance', 'Business / Commerciale', 'Supporto',
                'Controlli (II Livello)', 'Controlli (III Livello)', 'Controlli / Privacy',
            ])->comment('Macro area di appartenenza');
            $table->enum('name', [
                'Consiglio di Amministrazione / Direzione', 'Direzione Commerciale',
                'Gestione Rete e Collaboratori', 'Back Office / Istruttoria Pratiche',
                'Amministrazione e Contabilità', 'IT e Sicurezza Dati',
                'Marketing e Comunicazione', 'Gestione Reclami e Controversie',
                'Risorse Umane (HR) e Formazione', 'Compliance (Conformità)',
                'Risk Management', 'Antiriciclaggio (AML)',
                'Internal Audit (Revisione Interna)', 'Data Protection Officer (DPO)',
            ])->comment('Nome specifico funzione business');
            $table->enum('type', ['Strategica', 'Operativa', 'Supporto', 'Controllo'])
                ->comment('Tipologia funzione');
            $table->text('description')->nullable()->comment('Descrizione dettagliata funzione');
            $table->enum('outsourcable_status', ['yes', 'no', 'partial'])->default('no');
            $table->string('managed_by_code')->nullable();
            $table->longText('mission')->nullable()->comment('What does the function do');
            $table->longText('responsibility')->nullable()->comment('List of activities and responsibilities');
            $table->timestamps();

            $table->comment('Funzioni aziendali per funzionogramma');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_functions');
    }
};
