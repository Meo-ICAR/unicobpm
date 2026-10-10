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
        if (Schema::connection($this->connection)->hasColumn('email_templates', 'severity')) {
            return;
        }

        Schema::connection($this->connection)->table('email_templates', function (Blueprint $table) {
            // Grado di severity del check a cui il template risponde: ok, regular, warning, alert.
            // Nullable: i template esistenti (non legati a un check) restano invariati.
            $table->string('severity', 20)->nullable()->after('trigger_value');

            // Lo stesso code può ora avere un template per ciascuna severity.
            $table->dropUnique('email_templates_code_unique');
            $table->unique(['code', 'severity'], 'email_templates_code_severity_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->connection)->hasColumn('email_templates', 'severity')) {
            return;
        }

        Schema::connection($this->connection)->table('email_templates', function (Blueprint $table) {
            $table->dropUnique('email_templates_code_severity_unique');
            $table->dropColumn('severity');
            $table->unique('code', 'email_templates_code_unique');
        });
    }
};
