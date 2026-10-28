<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Politica di ripetizione degli avvisi dei controlli esterni: giorni di attesa prima di rimandare l'avviso per ogni grado
     * (per processo, sovrascrivibili nell'azione) e registro degli avvisi spediti, da cui si decide se rimandare o far salire di grado.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('processes', 'reminder_repeat_days')) {
            Schema::table('processes', function (Blueprint $table) {
                $table->json('reminder_repeat_days')->nullable()->comment('Giorni prima di rimandare un avviso dei controlli, per grado: {"regular": 7, "warning": 3, "alert": 2}; vuoto = valori predefiniti');
            });
        }

        if (Schema::hasTable('process_check_notifications')) {
            return;
        }

        Schema::create('process_check_notifications', function (Blueprint $table) {
            $table->id()->comment('Identificativo univoco del record');
            $table->unsignedBigInteger('process_task_item_id')->comment('Azione di controllo esterno che ha generato l\'avviso');
            $table->string('severity', 20)->comment('Grado restituito dal controllo (regular, warning, alert)');
            $table->string('effective_severity', 20)->comment('Grado con cui è partito l\'avviso: sale se il problema dura oltre i giorni di ripetizione');
            $table->unsignedSmallInteger('level')->default(1)->comment('Quante volte l\'avviso è stato mandato per questo problema');
            $table->timestamp('sent_at')->comment('Data e ora dell\'invio');
            $table->timestamp('resolved_at')->nullable()->comment('Quando il controllo è tornato ok: chiude il problema e la serie degli avvisi');
            $table->timestamps();

            $table->index(['process_task_item_id', 'resolved_at'], 'check_notifications_item_resolved_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_check_notifications');
    }
};
