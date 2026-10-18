<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * unicoloan è dismesso: l'istruttoria delle pratiche è in unicofinance. I processi che scrivono a fine istanza
 * (`completion_write_app`), le azioni che indicano l'app nella configurazione (`blacklist_check`, controlli esterni) e le
 * chiamate in uscita non ancora consegnate passano da 'unicoloan' a 'unicofinance'. Le chiamate già consegnate restano
 * com'erano, come storico.
 */
return new class extends Migration
{
    private const OLD = 'unicoloan';

    private const NEW = 'unicofinance';

    public function up(): void
    {
        $this->replace(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->replace(self::NEW, self::OLD);
    }

    private function replace(string $from, string $to): void
    {
        if (Schema::hasTable('processes')) {
            DB::table('processes')->where('completion_write_app', $from)->update(['completion_write_app' => $to]);
        }

        if (Schema::hasTable('process_task_items')) {
            DB::table('process_task_items')->where('config', 'like', '%'.$from.'%')->orderBy('id')->get(['id', 'config'])
                ->each(function (object $item) use ($from, $to): void {
                    $config = json_decode((string) $item->config, true);

                    if (! is_array($config) || ($config['app'] ?? null) !== $from) {
                        return;
                    }

                    $config['app'] = $to;
                    DB::table('process_task_items')->where('id', $item->id)->update(['config' => json_encode($config)]);
                });
        }

        if (Schema::hasTable('outbound_calls')) {
            // Anche meta.app, che il registro attività legge alla consegna della scrittura di fine processo.
            DB::table('outbound_calls')->where('app', $from)->whereIn('status', ['pending', 'failed'])->orderBy('id')->get(['id', 'meta'])
                ->each(function (object $call) use ($from, $to): void {
                    $meta = json_decode((string) $call->meta, true);

                    if (is_array($meta) && ($meta['app'] ?? null) === $from) {
                        $meta['app'] = $to;
                    }

                    DB::table('outbound_calls')->where('id', $call->id)->update([
                        'app' => $to,
                        'meta' => is_array($meta) ? json_encode($meta) : $call->meta,
                    ]);
                });
        }
    }
};
