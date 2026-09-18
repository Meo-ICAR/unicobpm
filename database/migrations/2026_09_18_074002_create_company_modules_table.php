<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabella vive nel database 'unicooam' (connessione mysql_unicooam),
     * accanto a companies e modules.
     */
    protected $connection = 'mysql_unicooam';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('company_modules')) {
            return;
        }

        Schema::connection($this->connection)->create('company_modules', function (Blueprint $table) {
            $table->comment('Moduli acquistati da ciascuna company, con piano e dati di fatturazione');

            $table->id();
            $table->uuid('company_id')->comment('Azienda che ha acquistato il modulo');
            $table->foreignId('module_id')
                ->constrained('modules')
                ->cascadeOnDelete();
            $table->string('plan_type')->nullable()->comment('Tipo di installazione/piano: BASE, MEDIUM, FULL');
            $table->date('trial_ends_at')->nullable()->comment('Data di termine del periodo di prova');
            $table->decimal('one_time_cost', 10, 2)->nullable()->comment('Costo una tantum di attivazione');
            $table->decimal('monthly_cost', 10, 2)->nullable()->comment('Costo ricorrente mensile');
            $table->string('billing_frequency')->nullable()->comment('Periodicità di fatturazione: mensile, trimestrale, semestrale, annuale');
            $table->date('last_invoice_at')->nullable()->comment('Data dell\'ultima fattura emessa');
            $table->date('last_payment_at')->nullable()->comment('Data dell\'ultimo incasso ricevuto');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('company_modules');
    }
};
