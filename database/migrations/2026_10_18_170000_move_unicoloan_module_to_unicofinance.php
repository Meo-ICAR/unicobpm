<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le aziende che avevano il modulo UNICOLOAN passano a UNICOFINANCE con lo stesso piano e gli stessi dati di fatturazione
 * (scelta di Pier, 2026-10-18). Se UNICOFINANCE non esiste ancora, il modulo UNICOLOAN si rinomina; se esiste, le righe delle
 * aziende passano al nuovo modulo e UNICOLOAN resta disattivato. Un'azienda che li ha già entrambi tiene le due righe, così
 * non si perde nessun dato di fatturazione. Il ritorno indietro non si ricostruisce: down() non fa nulla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection((new Module)->getConnectionName());

        $schema = Schema::connection($connection->getName());

        if (! $schema->hasTable('modules') || ! $schema->hasTable('company_modules')) {
            return;
        }

        $old = $connection->table('modules')->where('code', 'UNICOLOAN')->first();

        if ($old === null) {
            return;
        }

        $new = $connection->table('modules')->where('code', 'UNICOFINANCE')->first();
        $now = now();

        if ($new === null) {
            $connection->table('modules')->where('id', $old->id)->update(['code' => 'UNICOFINANCE', 'name' => 'UnicoFinance', 'description' => 'Istruttoria delle pratiche', 'updated_at' => $now]);

            return;
        }

        $connection->transaction(function () use ($connection, $old, $new, $now): void {
            // Gli id si leggono prima: MySQL non accetta una sottoquery sulla stessa tabella dell'UPDATE (errore 1093). La lettura
            // blocca le righe di UNICOFINANCE fino alla fine della transazione, così nessuno ne aggiunge una nel frattempo.
            $haGiaUnicofinance = $connection->table('company_modules')->where('module_id', $new->id)->lockForUpdate()->pluck('company_id')->all();

            $connection->table('company_modules')->where('module_id', $old->id)
                ->whereNotIn('company_id', $haGiaUnicofinance)
                ->update(['module_id' => $new->id, 'updated_at' => $now]);

            $connection->table('modules')->where('id', $old->id)->update(['is_active' => false, 'updated_at' => $now]);
        });
    }

    public function down(): void {}
};
