<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id()->comment('ID univoco checklist');
            $table->string('name')->nullable()->comment('Nome della checklist (es. Antiriciclaggio, Trasparenza)');
            $table->string('code')->nullable()->comment('Codice identificativo interno univoco');
            $table->text('description')->nullable()->comment('Descrizione generale della checklist e dei suoi scopi');
            $table->boolean('is_active')->default(true)->comment('Indica se la checklist è attualmente utilizzabile');
            // Regole di Compliance (Knockout)

            $table->timestamps();

            $table->comment('Anagrafica delle tipologie di checklist disponibili nel sistema');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklists');
    }
};
