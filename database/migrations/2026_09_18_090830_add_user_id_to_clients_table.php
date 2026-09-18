<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabella vive nel database 'proforma' (connessione proforma).
     *
     * Uniforma Client a Fornitore, che ha già una colonna user_id diretta
     * accanto alla relazione polimorfica user()/profile() (morphOne/morphTo
     * su users.profile_type/profile_id).
     */
    protected $connection = 'proforma';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasColumn('clients', 'user_id')) {
            return;
        }

        Schema::connection($this->connection)->table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('employee_roles')
                ->comment('ID dell\'utente collegato (account di login)');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('clients', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
