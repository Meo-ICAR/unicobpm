<?php

namespace App\Services;

use App\Models\BusinessFunction;
use App\Models\ProcessTaskExecution;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;
use Unico\Core\Enums\MailType;
use Unico\Core\Mail\EmailTemplateResolver;

/**
 * Le email dell'escalation di un task. Chi avvisa dipende dall'azione della regola:
 *  - `notify_assignee`: chi ha in carico la pratica, o, se nessuno l'ha presa, le funzioni Responsible (R) del task;
 *  - `notify_manager`: `manager_email` della regola o le funzioni Accountable (A);
 *  - `reassign`: la funzione `target_business_function` (id) a cui passa il task.
 * Testo dal template `email_template_code` della regola (default TASK_ESCALATION) per company, app unicobpm, tipo «processo» e grado
 * (livello 1 regular, 2 warning, dal 3 alert); senza template un testo semplice. Segnaposto: {pratica}, {riferimento}, {task}, {ore}, {livello}.
 */
class EscalationNotifier
{
    public function __construct(private EmailSendingService $mail, private EmailTemplateResolver $templates) {}

    /**
     * @param  array<string, mixed>  $rule
     * @return list<string> indirizzi a cui è partita l'email
     */
    public function notify(ProcessTaskExecution $execution, array $rule): array
    {
        $recipients = $this->recipients($execution, $rule);

        if ($recipients->isEmpty()) {
            return [];
        }

        [$subject, $body] = $this->compose($execution, $rule);
        $instance = $execution->processInstance;
        $sent = [];

        foreach ($recipients as $email) {
            try {
                $this->mail->send($email, $subject, $body, [], $instance->company_id, ['instance' => $instance->id, 'execution' => $execution->id]);
                $sent[] = $email;
            } catch (Throwable $e) {
                Log::error('Email di escalation non inviata.', ['execution' => $execution->id, 'error' => class_basename($e)]);
            }
        }

        return $sent;
    }

    /** @return Collection<int, string> */
    private function recipients(ProcessTaskExecution $execution, array $rule): Collection
    {
        $instance = $execution->processInstance;
        $task = $execution->processTask;

        $emails = match ($rule['action'] ?? null) {
            'notify_assignee' => $instance->currentAssignee?->email
                ? collect([$instance->currentAssignee->email])
                : $this->functionEmails($task->raciAssignments()->where('raci_role', 'R')->pluck('business_function_id')),
            'notify_manager' => filled($rule['manager_email'] ?? null)
                ? collect([$rule['manager_email']])
                : $this->functionEmails($task->raciAssignments()->where('raci_role', 'A')->pluck('business_function_id')),
            'reassign' => $this->functionEmails(collect([$rule['target_business_function'] ?? null])->filter()),
            default => collect(),
        };

        return $emails->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values();
    }

    /** @param Collection<int, int|string> $functionIds @return Collection<int, string> */
    private function functionEmails(Collection $functionIds): Collection
    {
        return BusinessFunction::query()->whereIn('id', $functionIds)->get()
            ->flatMap(fn (BusinessFunction $function) => $function->loginUsers()->pluck('email'))
            ->filter();
    }

    /** @return array{0: string, 1: string} */
    private function compose(ProcessTaskExecution $execution, array $rule): array
    {
        $instance = $execution->processInstance;
        $level = (int) ($rule['level'] ?? 1);
        $values = [
            '{pratica}' => $instance->title ?: "#{$instance->id}",
            '{riferimento}' => (string) ($instance->external_ref ?? ''),
            '{task}' => (string) $execution->processTask->name,
            '{ore}' => (string) $execution->created_at->diffInHours(now()),
            '{livello}' => (string) $level,
        ];
        $template = $this->templates->resolve((string) ($rule['email_template_code'] ?? 'TASK_ESCALATION'), $instance->company_id, 'unicobpm', MailType::Processo, match (true) {
            $level <= 1 => 'regular', $level === 2 => 'warning', default => 'alert',
        });

        if ($template !== null) {
            return [strtr($template->subject, $values), strtr($template->body, $values)];
        }

        return [
            strtr('Sollecito: {pratica} in attesa ({task})', $values),
            strtr('La pratica {pratica} è ferma al passo «{task}» da {ore} ore (sollecito di livello {livello}). Serve una presa in carico.', $values),
        ];
    }
}
