<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_submissions', function (Blueprint $table) {
            $table->id()->comment('ID univoco della compilazione');
            $table->unsignedBigInteger('checklist_id')->index()->comment('Riferimento alla checklist di base');
            //  $table->uuid('company_id')->index()->comment('Riferimento all\'azienda che sta compilando');
            $table->enum('status', ['draft', 'completed', 'approved', 'rejected'])
                ->default('draft')
                ->comment('Stato di avanzamento della compilazione');
            $table->timestamp('submitted_at')->nullable()->comment('Data e ora di invio definitivo');
            $table->text('notes')->nullable()->comment('Note generali sulla sottomissione');
            $table->timestamps();

            // Chiavi esterne
            $table->foreign('checklist_id')->references('id')->on('checklists')->cascadeOnDelete();
            // $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            $table->comment('Istanze di compilazione delle checklist da parte dei tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_submissions');
    }
};
