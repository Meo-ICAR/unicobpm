<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_unicooam';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('task_document_types')) {
            return;
        }

        Schema::connection($this->connection)->create('task_document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->cascadeOnDelete();
            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->cascadeOnDelete();
            $table->string('slug')->nullable()->comment('Slug univoco per URL');
            $table->boolean('is_required')->default(true)->comment('Se il documento è obbligatorio per questo task');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('task_document_types');
    }
};
