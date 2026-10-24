<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unisce i document_types duplicati per nome (e sistema il code TRASPARENZA riusato).
 * Ripunta documents, document_requirements, document_requests, process_task_items,
 * pdf_modules, task_document_types e renewed_by_id; soft-delete del duplicato.
 * Idempotente: se il duplicato è già soft-deleted non fa nulla di distruttivo.
 */
class MergeDuplicateDocumentTypesSeeder extends Seeder
{
    /**
     * Survivor => [loser, attributi da copiare sul survivor se mancanti].
     *
     * @var array<int, array{0: int, 1: array<string, mixed>}>
     */
    private const MERGES = [
        // Polizza: tiene 12 (slug + documenti/plichi), prende il code da 49.
        12 => [49, ['code' => 'POLIZZA_RC_PROFESSIONALE']],
        // Visura: tiene 37 (più documents), prende il code da 43.
        37 => [43, ['code' => 'VISURA_CAMERALE', 'name' => 'Visura Camerale']],
        // Relazione audit: tiene 33, elimina 34.
        33 => [34, []],
    ];

    public function run(): void
    {
        foreach (self::MERGES as $keepId => [$dropId, $enrich]) {
            $this->merge($keepId, $dropId, $enrich);
        }

        $this->fixTrasparenzaCodes();
    }

    /**
     * @param  array<string, mixed>  $enrich
     */
    private function merge(int $keepId, int $dropId, array $enrich): void
    {
        $keep = DB::table('document_types')->where('id', $keepId)->first();
        $drop = DB::table('document_types')->where('id', $dropId)->first();

        if (! $keep) {
            $this->command?->warn("Merge document_types: survivor #{$keepId} assente, skip.");

            return;
        }

        if (! $drop || $drop->deleted_at !== null) {
            $this->command?->info("Merge document_types: #{$dropId} già assente/soft-deleted.");

            // Arricchisce comunque il survivor (utile dopo un primo passaggio).
            if ($enrich !== []) {
                $this->enrichKeeper($keepId, $enrich);
            }

            return;
        }

        $usage = $this->usageReport($dropId);
        $this->command?->info("Unione #{$dropId} → #{$keepId} (uso loser: {$usage})");

        DB::transaction(function () use ($keepId, $dropId, $drop, $enrich): void {
            $this->repoint('documents', 'document_type_id', $dropId, $keepId);
            $this->repointRequirements('document_requirements', $dropId, $keepId);
            $this->repointRequirements('task_document_types', $dropId, $keepId);
            $this->repoint('document_requests', 'document_type_id', $dropId, $keepId);
            $this->repoint('process_task_items', 'document_type_id', $dropId, $keepId);
            $this->repoint('pdf_modules', 'document_type_id', $dropId, $keepId);

            if (Schema::hasColumn('document_types', 'renewed_by_id')) {
                DB::table('document_types')->where('renewed_by_id', $dropId)->update(['renewed_by_id' => $keepId]);
            }

            // Libera code/slug sul loser prima di copiarli sul survivor (unique index).
            DB::table('document_types')->where('id', $dropId)->update([
                'code' => null,
                'slug' => $drop->slug ? ($drop->slug.'-merged-'.$dropId) : null,
                'updated_at' => now(),
            ]);

            $this->enrichKeeper($keepId, $enrich);

            DB::table('document_types')->where('id', $dropId)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $enrich
     */
    private function enrichKeeper(int $keepId, array $enrich): void
    {
        if ($enrich === []) {
            return;
        }

        $keep = DB::table('document_types')->where('id', $keepId)->first();
        $payload = ['updated_at' => now()];

        foreach ($enrich as $field => $value) {
            if ($keep->{$field} === null || $keep->{$field} === '') {
                $payload[$field] = $value;
            }
        }

        if (count($payload) > 1) {
            DB::table('document_types')->where('id', $keepId)->update($payload);
        }
    }

    private function repoint(string $table, string $column, int $from, int $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->where($column, $from)->update([$column => $to]);
    }

    /**
     * Per plichi: se survivor e loser sono entrambi sullo stesso task, elimina la riga loser.
     */
    private function repointRequirements(string $table, int $from, int $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'document_type_id')) {
            return;
        }

        if (! Schema::hasColumn($table, 'task_id')) {
            $this->repoint($table, 'document_type_id', $from, $to);

            return;
        }

        $rows = DB::table($table)->where('document_type_id', $from)->get(['id', 'task_id']);

        foreach ($rows as $row) {
            $exists = DB::table($table)
                ->where('task_id', $row->task_id)
                ->where('document_type_id', $to)
                ->where('id', '!=', $row->id)
                ->exists();

            if ($exists) {
                DB::table($table)->where('id', $row->id)->delete();
            } else {
                DB::table($table)->where('id', $row->id)->update(['document_type_id' => $to]);
            }
        }
    }

    private function usageReport(int $id): string
    {
        $parts = [];

        foreach ([
            'documents' => 'document_type_id',
            'document_requirements' => 'document_type_id',
            'task_document_types' => 'document_type_id',
            'document_requests' => 'document_type_id',
            'process_task_items' => 'document_type_id',
            'pdf_modules' => 'document_type_id',
        ] as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = DB::table($table)->where($column, $id)->count();

            if ($count > 0) {
                $parts[] = "{$table}={$count}";
            }
        }

        return $parts === [] ? 'nessun riferimento' : implode(', ', $parts);
    }

    /**
     * I tre tipi con code TRASPARENZA non sono lo stesso documento: code → codegroup, code univoci.
     */
    private function fixTrasparenzaCodes(): void
    {
        $map = [
            15 => ['code' => 'FOGLIO_INFORMATIVO', 'codegroup' => 'TRASPARENZA'],
            16 => ['code' => 'TRASPARENZA_WEB', 'codegroup' => 'TRASPARENZA'],
            31 => ['code' => 'TEGM_WEB', 'codegroup' => 'TRASPARENZA'],
        ];

        $ids = array_keys($map);

        // Svuota prima i code duplicati (unique), poi assegna univoci.
        DB::table('document_types')->whereIn('id', $ids)->update([
            'code' => null,
            'codegroup' => 'TRASPARENZA',
            'updated_at' => now(),
        ]);

        foreach ($map as $id => $attrs) {
            $row = DB::table('document_types')->where('id', $id)->whereNull('deleted_at')->first();

            if (! $row) {
                continue;
            }

            DB::table('document_types')->where('id', $id)->update([
                'code' => $attrs['code'],
                'codegroup' => $attrs['codegroup'],
                'updated_at' => now(),
            ]);
        }
    }
}
