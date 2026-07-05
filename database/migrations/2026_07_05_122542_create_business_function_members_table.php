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
        Schema::create('business_function_members', function (Blueprint $table) {
            $table->id(); // ID univoco per il record pivot

            // Foreign key verso la business function
            $table->foreignId('business_function_id')
                ->constrained('business_functions')
                ->cascadeOnDelete();

            // Campi polimorfici: member_type (string) e member_id (bigint)
            $table->morphs('member');

            // Flag aggiuntivi per la pivot
            $table->boolean('is_manager')->default(false)->comment('Indica se l\'utente è responsabile/manager di questa funzione');

            $table->timestamps();

            // Indice univoco per evitare che la stessa persona venga assegnata più volte alla stessa funzione
            $table->unique(
                ['business_function_id', 'member_id', 'member_type'],
                'unique_business_function_member'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_function_members');
    }
};
