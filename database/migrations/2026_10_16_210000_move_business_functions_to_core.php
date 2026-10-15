<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Funzioni aziendali e membri passano al database condiviso (tabelle `business_functions` e `business_function_members`
 * gestite da unico-core). Si copiano con gli stessi id, quindi la matrice RACI (`process_task_raci`) e i passi dei processi
 * (`process_tasks`) continuano a puntare alla funzione giusta; poi si tolgono le chiavi esterne e le tabelle locali.
 * Con la connessione condivisa uguale a quella dell'app (test) non c'è nulla da spostare.
 */
return new class extends Migration
{
    public function up(): void
    {
        $shared = config('unico-core.connection');

        if ($shared === config('database.default') || ! Schema::hasTable('business_functions')) {
            return;
        }

        $core = DB::connection($shared);

        if (! $core->getSchemaBuilder()->hasTable('business_functions')) {
            throw new RuntimeException('Le tabelle delle funzioni aziendali non esistono nel database condiviso: eseguire prima le migrazioni di unico-core.');
        }

        foreach (DB::table('business_functions')->orderBy('id')->get() as $function) {
            $core->table('business_functions')->updateOrInsert(['id' => $function->id], (array) $function);
        }

        // Il tipo del membro passa agli alias del core (employee, client).
        $alias = ['App\\Models\\Employee' => 'employee', 'App\\Models\\Client' => 'client'];

        foreach (DB::table('business_function_members')->orderBy('id')->get() as $member) {
            $core->table('business_function_members')->updateOrInsert(['id' => $member->id], ['member_type' => $alias[$member->member_type] ?? $member->member_type] + (array) $member);
        }

        foreach (DB::select('select table_name as t, constraint_name as c from information_schema.key_column_usage where table_schema = database() and referenced_table_name in (?, ?)', ['business_functions', 'business_function_members']) as $fk) {
            if (! in_array($fk->t, ['business_function_members', 'business_functions'], true)) {
                Schema::table($fk->t, fn (Blueprint $table) => $table->dropForeign($fk->c));
            }
        }

        Schema::dropIfExists('business_function_members');
        Schema::dropIfExists('business_functions');
    }

    public function down(): void
    {
        // Le tabelle restano nel database condiviso.
    }
};
