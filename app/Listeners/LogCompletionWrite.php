<?php

namespace App\Listeners;

use App\Events\OutboundCallSucceeded;
use App\Models\ProcessInstance;

/** Alla consegna della scrittura di completamento registra l'attività sul processo (prima lo faceva l'observer, senza sapere se era arrivata). */
class LogCompletionWrite
{
    public function handle(OutboundCallSucceeded $event): void
    {
        $call = $event->call;

        if ($call->event !== 'completion_write') {
            return;
        }

        $instance = ProcessInstance::query()->find($call->meta['instance_id'] ?? 0);

        if ($instance === null) {
            return;
        }

        $field = (string) ($call->meta['field'] ?? '');
        $app = (string) ($call->meta['app'] ?? '');

        activity('bpm')
            ->performedOn($instance)
            ->event('completion_field_written')
            ->withProperties(['field' => $field, 'value' => (string) ($call->meta['value'] ?? ''), 'app' => $app])
            ->log("Scritto {$field} sul soggetto della pratica al completamento (via API {$app}).");
    }
}
