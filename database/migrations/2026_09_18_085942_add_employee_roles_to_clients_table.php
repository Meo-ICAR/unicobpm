<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabella vive nel database 'proforma' (connessione proforma).
     */
    protected $connection = 'proforma';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasColumn('clients', 'employee_roles')) {
            return;
        }

        Schema::connection($this->connection)->table('clients', function (Blueprint $table) {
            $table->json('employee_roles')->nullable()->after('client_type_id')
                ->comment('Ruoli (nomi EmployeeType) associati a questo client (consulente esterno o cliente finale), stessa struttura di employees.employee_roles');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('clients', function (Blueprint $table) {
            $table->dropColumn('employee_roles');
        });
    }
};
