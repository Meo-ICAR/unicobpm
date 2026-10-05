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
        Schema::create('process_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('model_class'); // Es: 'App\Models\Contratto' o 'App\Models\Fattura'
            $table->foreignId('process_id')->constrained('processes')->cascadeOnDelete();

            $table->string('event_type'); // 'created', 'updated', 'idle'
            $table->json('conditions')->nullable(); // Es: [{"field": "status", "operator": "=", "value": "sospeso"}]
            $table->integer('idle_days')->nullable(); // Usato solo per event_type = 'idle' (es. 5 giorni)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_triggers');
    }
};
