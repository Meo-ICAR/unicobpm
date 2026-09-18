<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_type_resource_presets')) {
            return;
        }

        Schema::create('employee_type_resource_presets', function (Blueprint $table) {
            $table->comment('Preset di accesso totale (tutte le azioni CRUD) per ruolo/risorsa, affiancati alla matrice granulare employee_type_permissions.');

            $table->id()->comment('ID univoco del preset.');

            // Usiamo integer per combaciare con l'INT della tabella employee_types
            // (stessa scelta di employee_type_permissions.employee_type_id).
            $table->integer('employee_type_id')->comment('Ruolo (EmployeeType) a cui è assegnato il preset.');

            $table->foreignId('resource_id')
                ->comment('Risorsa/procedura applicativa sbloccata per intero da questo preset.')
                ->constrained('resources')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->foreign('employee_type_id')
                ->references('id')
                ->on('employee_types')
                ->cascadeOnDelete();

            $table->unique(['employee_type_id', 'resource_id'], 'emp_type_resource_preset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_type_resource_presets');
    }
};
