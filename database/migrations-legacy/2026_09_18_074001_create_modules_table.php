<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabella vive nel database 'unicooam' (connessione mysql_unicooam),
     * come già Company/Employee/DocumentType/Task.
     */
    protected $connection = 'mysql_unicooam';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('modules')) {
            return;
        }

        Schema::connection($this->connection)->create('modules', function (Blueprint $table) {
            $table->comment('Elenco dei moduli/prodotti disponibili nella suite');

            $table->id();
            $table->string('code')->unique()->comment('Codice mnemonico univoco del modulo (es. UNICOBPM)');
            $table->string('name')->comment('Nome commerciale del modulo');
            $table->string('description')->nullable()->comment('Descrizione della funzione del modulo');
            $table->boolean('is_active')->default(true)->comment('Modulo attualmente commercializzabile');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('modules');
    }
};
