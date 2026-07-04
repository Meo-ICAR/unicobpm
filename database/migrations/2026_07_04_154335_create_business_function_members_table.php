<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_function_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_function_id')->constrained('business_functions')->cascadeOnDelete();

            // Collega dinamicamente a un modello Employer o Consultant
            $table->morphs('member');

            $table->boolean('is_manager')->default(false);
            $table->timestamps();

            $table->unique(['business_function_id', 'member_type', 'member_id'], 'unique_function_member');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_function_members');
    }
};
