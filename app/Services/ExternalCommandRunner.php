<?php

namespace App\Services;

use App\Models\ProcessInstance;
use App\Models\ProcessTaskItem;

/**
 * Esegue un'azione 'external_command': accoda sull'applicativo un comando in whitelist (POST /api/commands/{comando}, bridge di
 * unico-core) tramite la coda delle chiamate in uscita, quindi con ritentativi e una sola volta per pratica e azione.
 *
 * config dell'item: {"app": "unicocoge", "command": "proformas:send-pending", "options": {"dry-run": true}, "cc_raci": ["A", "I"]}
 * Con `cc_raci` (lettere della RACI del passo) le email di chi ha quei ruoli diventano l'opzione `cc` del comando, separate da virgola.
 */
class ExternalCommandRunner
{
    public function __construct(private OutboundCallService $calls, private ExternalCheckRunner $checks) {}

    /**
     * @return string Riepilogo dell'esito, da registrare come risposta dell'item.
     */
    public function run(ProcessTaskItem $item, ProcessInstance $instance): string
    {
        $app = $item->config['app'] ?? null;
        $command = $item->config['command'] ?? null;

        if (blank($app) || blank($command)) {
            return 'Comando non configurato (app o comando mancanti).';
        }

        $options = (array) ($item->config['options'] ?? []);
        $cc = $this->checks->recipientsForRoles($item->task, (array) ($item->config['cc_raci'] ?? []))->all();

        if ($cc !== []) {
            $options['cc'] = implode(',', $cc);
        }

        $this->calls->enqueue(
            'external_command', $app, 'POST', "/api/commands/{$command}", ['options' => $options],
            ['instance_id' => $instance->id, 'item_id' => $item->id, 'command' => $command],
            "command:{$instance->id}:{$item->id}",
        );

        return "Comando {$app}/{$command} accodato".($cc === [] ? '' : ' (copia conoscenza: '.implode(', ', $cc).')').'.';
    }
}
