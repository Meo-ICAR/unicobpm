<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabella vive nel database 'unicooam' (connessione mysql_unicooam),
     * come già Employee/DocumentType/Task.
     */
    protected $connection = 'mysql_unicooam';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('tasks')) {
            return;
        }

        Schema::connection($this->connection)->create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('tasks')
                ->cascadeOnDelete();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('app_identifier', 50)->nullable();
            $table->string('taskable')->nullable();
            $table->string('trigger_field')->nullable()->comment('Campo del modello da controllare');
            $table->string('trigger_state')->nullable()->comment('filled, empty, equals');
            $table->string('trigger_value')->nullable()->comment('Il valore specifico da controllare');
            $table->string('exclude_field')->nullable()->comment('Campo del modello da escludere se valorizzato');
            $table->string('exclude_state')->nullable()->comment('filled, empty, equals');
            $table->string('exclude_value')->nullable()->comment('Il valore specifico da controllare');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('app_identifier');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('tasks');
    }
};
