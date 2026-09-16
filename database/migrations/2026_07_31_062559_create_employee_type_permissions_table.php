<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_type_permissions')) {
            return;
        }

        Schema::create('employee_type_permissions', function (Blueprint $table) {
            $table->comment('Permessi assegnati a ciascuna tipologia di dipendente sulle risorse applicative.');

            $table->id()->comment('ID univoco del permesso.');

            // Usiamo integer per combaciare con l'INT della tabella employee_types
            $table->integer('employee_type_id')->comment('Tipologia di dipendente a cui è assegnato il permesso.');

            // FK reale su resources.id: resources.key è unico solo per (app_name, key),
            // non globalmente, quindi una stringa "resource" sciolta sarebbe ambigua
            // se più app condividessero la stessa key.
            $table->foreignId('resource_id')->comment('Risorsa/funzionalità applicativa a cui si riferisce il permesso.');

            $table->enum('action', ['viewAny', 'view', 'create', 'update', 'delete'])
                ->default('viewAny')
                ->comment('Azione consentita sulla risorsa.');

            $table->timestamps();

            // Foreign Keys
            $table->foreign('employee_type_id')
                ->references('id')
                ->on('employee_types')
                ->cascadeOnDelete();

            $table->foreign('resource_id')
                ->references('id')
                ->on('resources')
                ->cascadeOnDelete();

            // passiamo 'emp_type_perm_unique' come 2° argomento per forzare un nome breve all'indice
            $table->unique(['employee_type_id', 'resource_id', 'action'], 'emp_type_perm_unique');

            // Indice separato: la composita sopra ha employee_type_id come colonna
            // iniziale, quindi non copre le query filtrate solo per resource_id
            // (es. Resource::permissions() nella relation manager inversa).
            $table->index('resource_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_type_permissions');
    }
};
