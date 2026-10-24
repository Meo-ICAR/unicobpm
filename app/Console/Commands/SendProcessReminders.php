<?php

namespace App\Console\Commands;

use App\Models\ProcessInstance;
use App\Services\EmailSendingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;
use Unico\Core\Enums\MailType;
use Unico\Core\Mail\EmailTemplateResolver;

/**
 * Solleciti alla controparte delle pratiche ferme su un passo con «solleciti attivi» (`has_reminders`): ogni `reminder_interval_days`
 * giorni dall'inizio del passo (o dall'ultimo sollecito), fino a `max_reminders` per passo. L'email (template `PROCESS_REMINDER` per
 * company, app unicobpm, tipo processo) elenca i documenti mancanti e parte con l'identificativo del processo: la risposta con gli
 * allegati soddisfa da sola le azioni di caricamento (HandleProcessEmailReply). Pianificato ogni settimana.
 */
class SendProcessReminders extends Command
{
    protected $signature = 'bpm:send-reminders';

    protected $description = 'Invia solleciti automatici per i task documentali pendenti';

    public function handle(EmailSendingService $mailer, EmailTemplateResolver $templates): int
    {
        $sent = 0;

        $instances = ProcessInstance::query()
            ->where('status', 'in_progress')
            ->whereHas('currentTask', fn ($query) => $query->where('has_reminders', true))
            ->with(['currentTask', 'subject'])
            ->get();

        foreach ($instances as $instance) {
            $task = $instance->currentTask;
            $execution = $instance->currentTaskExecution;

            if ($execution === null) {
                continue;
            }

            // Contatore e data valgono per il passo corrente: un sollecito di un passo precedente non conta.
            $sameStep = $instance->last_reminder_sent_at !== null && $instance->last_reminder_sent_at->gte($execution->created_at);
            $count = $sameStep ? (int) $instance->reminders_sent_count : 0;
            $since = $sameStep ? $instance->last_reminder_sent_at : $execution->created_at;

            if ($count >= (int) ($task->max_reminders ?? 3) || $since->diffInDays(now()) < (int) ($task->reminder_interval_days ?? 7)) {
                continue;
            }

            $email = $instance->subject?->email ?? data_get($instance->external_data, 'email');

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->warn("Pratica {$instance->id}: nessuna email della controparte, sollecito non inviato.");

                continue;
            }

            [$subject, $body] = $this->compose($templates, $instance, $execution->pendingItemsOfType(['document_upload'])->pluck('name')->all(), $count + 1);

            try {
                $mailer->send($email, $subject, $body, companyId: $instance->company_id, thread: ['instance' => $instance->id, 'execution' => $execution->id]);
            } catch (Throwable $e) {
                Log::warning("Sollecito della pratica {$instance->id} non inviato.", ['error' => $e->getMessage()]);

                continue;
            }

            $instance->update(['last_reminder_sent_at' => now(), 'reminders_sent_count' => $count + 1]);
            $sent++;
            $this->info("Sollecito inviato per la pratica {$instance->id} a {$email}.");
        }

        $this->info("Solleciti inviati: {$sent}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $documents
     * @return array{0: string, 1: string}
     */
    private function compose(EmailTemplateResolver $templates, ProcessInstance $instance, array $documents, int $number): array
    {
        $values = [
            '{pratica}' => $instance->title ?: "#{$instance->id}",
            '{task}' => (string) $instance->currentTask->name,
            '{documenti}' => $documents === [] ? '- (vedi richiesta precedente)' : '- '.implode("\n- ", $documents),
            '{numero}' => (string) $number,
        ];

        $template = $templates->resolve('PROCESS_REMINDER', $instance->company_id, 'unicobpm', MailType::Processo);

        if ($template !== null) {
            return [strtr($template->subject, $values), strtr($template->body, $values)];
        }

        return [
            strtr('Sollecito: documenti mancanti per {pratica}', $values),
            strtr("Per proseguire con la pratica {pratica} mancano ancora:\n{documenti}\n\nPuò rispondere a questa email allegando i documenti.", $values),
        ];
    }
}
