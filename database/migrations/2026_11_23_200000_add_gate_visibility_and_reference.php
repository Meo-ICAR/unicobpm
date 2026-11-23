<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per il processo di reclutamento della rete (e valide per qualunque processo):
 *  - `checklists.is_gate`: gate bloccante, la checklist si approva solo con tutte le voci a «sì» (altrimenti la fase non avanza);
 *  - `processes.visibility_bcc`: indirizzi (separati da virgola) che ricevono in copia nascosta ogni email del processo, così le
 *    comunicazioni sono visibili anche in unicopec (es. recruiting@races.it);
 *  - `processes.reference_prefix` e `process_instances.reference`: codice pratica (es. REC-2026-0001), usato anche nell'oggetto delle email.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('checklists', 'is_gate')) {
            Schema::table('checklists', fn (Blueprint $table) => $table->boolean('is_gate')->default(false)->after('is_active'));
        }

        Schema::table('processes', function (Blueprint $table) {
            if (! Schema::hasColumn('processes', 'visibility_bcc')) {
                $table->string('visibility_bcc')->nullable();
            }

            if (! Schema::hasColumn('processes', 'reference_prefix')) {
                $table->string('reference_prefix', 10)->nullable();
            }
        });

        if (! Schema::hasColumn('process_instances', 'reference')) {
            Schema::table('process_instances', fn (Blueprint $table) => $table->string('reference', 40)->nullable()->index()->after('external_ref'));
        }
    }

    public function down(): void
    {
        foreach ([['process_instances', 'reference'], ['processes', 'reference_prefix'], ['processes', 'visibility_bcc'], ['checklists', 'is_gate']] as [$table, $column]) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
