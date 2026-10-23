<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_task_executions', function (Blueprint $table) {
            if (Schema::hasColumn('process_task_executions', 'postponed_until')) {
                return;
            }

            // Rinvio concordato con la controparte: il termine dello step non scade prima di questa data e l'escalation resta ferma.
            $table->date('postponed_until')->nullable()->after('mandatory_days_to_complete');
        });

        if (! Schema::hasTable('process_postponements')) {
            Schema::create('process_postponements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_instance_id')->constrained('process_instances')->cascadeOnDelete();
                $table->foreignId('process_task_execution_id')->constrained('process_task_executions')->cascadeOnDelete();
                $table->unsignedBigInteger('process_email_reply_id')->nullable()->comment('Risposta email che l\'ha chiesto, se c\'è');
                $table->dateTime('from_at')->nullable()->comment('Termine dello step prima del rinvio');
                $table->date('to_date')->comment('Nuovo termine concordato');
                $table->unsignedBigInteger('granted_by')->nullable()->comment('users.id di chi l\'ha concesso; nullo = automatico');
                $table->boolean('moved_hard_deadline')->default(false)->comment('Spostata anche la data tassativa della pratica');
                $table->string('reason', 500)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('process_postponements');
        Schema::table('process_task_executions', fn (Blueprint $table) => $table->dropColumn('postponed_until'));
    }
};
