<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * subject_type/subject_id erano NOT NULL, ma StartProcessAction (usata anche dallo scheduler BPM
     * per i processi interni/ricorrenti senza un soggetto specifico) crea pratiche con subject = null.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE process_instances MODIFY COLUMN subject_type VARCHAR(255) NULL');
            DB::statement('ALTER TABLE process_instances MODIFY COLUMN subject_id BIGINT UNSIGNED NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE process_instances MODIFY COLUMN subject_type VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE process_instances MODIFY COLUMN subject_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
