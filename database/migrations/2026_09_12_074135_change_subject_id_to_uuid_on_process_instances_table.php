<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `process_instances.subject_id` was created as `bigint unsigned` (Laravel's
 * `nullableMorphs()` default), but every real subject model (Fornitore,
 * Clienti, Client) uses a UUID primary key — starting a process against a
 * real subject always failed with "Data truncated for column 'subject_id'".
 * No row has ever successfully populated this column (confirmed before
 * writing this migration), so a plain type change is safe. sqlite (tests)
 * gets the corrected type straight from the original create-table migration
 * instead, since sqlite has no MODIFY COLUMN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE process_instances MODIFY COLUMN subject_id CHAR(36) NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE process_instances MODIFY COLUMN subject_id BIGINT UNSIGNED NULL');
        }
    }
};
