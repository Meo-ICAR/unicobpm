<?php

namespace App\Services;

use App\Enums\Severity;
use App\Models\EmployeeType;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Esegue un'azione 'external_check': l'applicativo esterno (GET /api/checks/{command}) restituisce
 * solo lo stato del controllo, {"value": ..., "severity": "ok|regular|warning|alert", "details": "..." (opzionale)}; qui si decide
 * cosa farne: si sceglie l'EmailTemplate (code + severity) e si scrive ai ruoli RACI del task
 * padre previsti per quella severity (vedi Severity::raciRolesToNotify()).
 *
 * config dell'item: {"app": "unicocoge", "command": "clienti:check-missing-piva", "email_template_code": "CHECK_PIVA"}
 */
class ExternalCheckRunner
{
    public function __construct(
        protected ExternalAppResolver $appResolver,
        protected EmailSendingService $emailSender,
    ) {}

    protected ?ReminderPolicy $policy = null;

    protected function policy(): ReminderPolicy
    {
        return $this->policy ??= new ReminderPolicy;
    }

    /**
     * @return string Riepilogo dell'esito, da registrare come risposta dell'item.
     */
    public function run(ProcessTaskItem $item, ?ProcessInstance $instance = null): string
    {
        $app = $item->config['app'] ?? null;
        $command = $item->config['command'] ?? null;

        if (blank($app) || blank($command)) {
            Log::warning("Item ID {$item->id}: external_check senza app o command configurati.");

            return 'Check non configurato (app o comando mancanti).';
        }

        $result = $this->fetchStatus($app, $command);

        if ($result === null) {
            return "Check {$app}/{$command} non disponibile: nessuna email inviata.";
        }

        ['value' => $value, 'severity' => $severity, 'details' => $details, 'attachment' => $attachment] = $result;

        if ($severity === Severity::Ok) {
            $this->policy()->resolve($item);

            return "Check {$app}/{$command}: valore {$value}, severity ok — nessuna email da inviare.";
        }

        // Un avviso già mandato non si ripete ogni giorno: si attendono i giorni del grado, poi l'avviso sale di grado.
        $checked = $severity ?? Severity::Alert;
        $toSend = $this->policy()->severityToSend($item, $checked);

        if ($toSend === null) {
            return "Check {$app}/{$command}: valore {$value}, severity {$checked->value} — avviso già inviato, il prossimo dopo {$this->policy()->repeatDays($item, $checked)} giorni dall'ultimo.";
        }

        $severity = $toSend;
        $template = $item->resolveCheckEmailTemplate($severity, $instance?->company_id);

        if (! $template) {
            return "Check {$app}/{$command}: valore {$value}, severity ".($severity?->value ?? 'assente').' — nessun template email trovato.';
        }

        $effectiveSeverity = $severity ?? $template->severity;
        // Con `to_raci` (es. ["A", "I"]) scrivono quei ruoli della RACI del passo, qualunque sia la severity; altrimenti quelli previsti dalla severity.
        $toRaci = (array) ($item->config['to_raci'] ?? []);
        $recipients = $toRaci !== []
            ? $this->recipientsForRoles($item->task, $toRaci)
            : $this->recipientsFor($item->task, $effectiveSeverity);
        $isFallback = $recipients->isEmpty();
        $recipients = $isFallback ? FallbackRecipient::emails() : $recipients;

        if ($recipients->isEmpty()) {
            return "Check {$app}/{$command}: valore {$value}, severity {$effectiveSeverity->value} — nessun destinatario da avvisare.";
        }

        $subject = $this->fill($template->subject, $value, $details, $effectiveSeverity, $item, $instance);
        $body = $this->fill($template->body, $value, $details, $effectiveSeverity, $item, $instance);

        $sent = collect();
        $failed = collect();

        $attachmentPath = $this->writeAttachment($attachment);
        $files = $attachmentPath === null ? [] : [['path' => $attachmentPath, 'as' => $attachment['filename']]];

        foreach ($recipients as $email) {
            try {
                $this->emailSender->send($email, $subject, $body, $files, $instance?->company_id, $instance ? ['instance' => $instance->id] : null);
                $sent->push($email);
            } catch (\Throwable $e) {
                Log::error("Check {$app}/{$command}: invio email a {$email} fallito.", ['error' => $e->getMessage()]);
                $failed->put($email, $e->getMessage());
            }
        }

        if ($attachmentPath !== null) {
            @unlink($attachmentPath);
        }

        if ($sent->isNotEmpty()) {
            $this->policy()->recordSent($item, $checked, $effectiveSeverity);
        }

        $label = "Check {$app}/{$command}: valore {$value}, severity {$effectiveSeverity->value}";

        $summary = $label.' — '.($sent->isEmpty() ? 'nessuna email inviata' : 'email inviata a '.$sent->implode(', ').($isFallback ? ' (amministratore: nessun destinatario con utente o email)' : '')).'.';

        if ($failed->isNotEmpty()) {
            $summary .= ' Invio FALLITO per: '.$failed->map(fn (string $error, string $email) => "{$email} ({$error})")->implode('; ').'.';
        }

        $this->sendOutcomeReport($item->task, $effectiveSeverity, $label, $sent, $failed);

        return $summary;
    }

    /**
     * Per i check in warning/alert avvisa i responsabili (ruolo A) di chi è stato raggiunto e di chi no,
     * così un invio fallito non passa inosservato. Un errore qui non deve mai bloccare la pratica.
     *
     * @param  Collection<int, string>  $sent
     * @param  Collection<string, string>  $failed
     */
    private function sendOutcomeReport(ProcessTask $task, Severity $severity, string $label, Collection $sent, Collection $failed): void
    {
        if (! in_array($severity, [Severity::Warning, Severity::Alert], true)) {
            return;
        }

        $body = "{$label}\n\nEmail inviate a: ".($sent->isEmpty() ? 'nessuno' : $sent->implode(', '));

        if ($failed->isNotEmpty()) {
            $body .= "\n\nInvio FALLITO per:\n".$failed->map(fn (string $error, string $email) => "- {$email}: {$error}")->implode("\n");
        }

        foreach ($this->recipientsForRoles($task, ['A']) as $email) {
            try {
                $this->emailSender->send($email, "Esito invio avvisi ({$severity->label()}): {$label}", $body);
            } catch (\Throwable $e) {
                Log::error("Esito invio avvisi a {$email} fallito.", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Indirizzi delle funzioni aziendali assegnate al task con i ruoli RACI previsti dalla severity.
     * Se la funzione non ha un'email propria si ripiega sugli utenti di login dei suoi membri.
     *
     * @return Collection<int, string>
     */
    public function recipientsFor(ProcessTask $task, Severity $severity): Collection
    {
        return $this->recipientsForRoles($task, $severity->raciRolesToNotify());
    }

    /**
     * @param  array<int, string>  $roles  Ruoli RACI (R/A/C/I).
     * @return Collection<int, string>
     */
    public function recipientsForRoles(ProcessTask $task, array $roles): Collection
    {
        if ($roles === []) {
            return collect();
        }

        return $task->raciAssignments()
            ->whereIn('raci_role', $roles)
            ->with('employeeType')
            ->get()
            ->flatMap(fn ($assignment) => $this->emailsOf($assignment->employeeType))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Email di chi ricopre il ruolo, dall'anagrafica (non serve un account di accesso); se nessuno lo ricopre, quella dell'ufficio.
     *
     * @return Collection<int, string>
     */
    private function emailsOf(?EmployeeType $role): Collection
    {
        return $role ? $role->memberEmails() : collect();
    }

    /**
     * @return array{value: string, severity: ?Severity, details: string, attachment: ?array{filename: string, content: string}}|null null se l'applicativo non risponde.
     */
    private function fetchStatus(string $app, string $command): ?array
    {
        try {
            $client = app(ExternalAppClient::class);
            $response = $client->for($app)->get($client->url($app, "api/checks/{$command}"));
        } catch (\Throwable $e) {
            Log::warning("Check {$app}/{$command} fallito per errore di rete.", ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning("Check {$app}/{$command} rifiutato.", ['status' => $response->status()]);

            return null;
        }

        return [
            'value' => (string) $response->json('value', ''),
            'severity' => Severity::tryFrom((string) $response->json('severity')),
            'details' => (string) $response->json('details', ''),
            'attachment' => $this->attachmentFrom($response->json('attachment')),
        ];
    }

    /**
     * @return array{filename: string, content: string}|null
     */
    private function attachmentFrom(mixed $attachment): ?array
    {
        if (! is_array($attachment) || blank($attachment['filename'] ?? null) || ! is_string($attachment['content'] ?? null)) {
            return null;
        }

        return ['filename' => basename((string) $attachment['filename']), 'content' => $attachment['content']];
    }

    /**
     * Scrive l'allegato del check in un file temporaneo (da cancellare dopo l'invio).
     *
     * @param  array{filename: string, content: string}|null  $attachment
     */
    private function writeAttachment(?array $attachment): ?string
    {
        if ($attachment === null) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'bpm-check-');

        return $path !== false && file_put_contents($path, $attachment['content']) !== false ? $path : null;
    }

    /**
     * Placeholder disponibili oltre a quelli della pratica: {value}, {severity}, {details}.
     */
    private function fill(?string $text, string $value, string $details, Severity $severity, ProcessTaskItem $item, ?ProcessInstance $instance): string
    {
        $text = str_replace(['{value}', '{severity}', '{details}'], [$value, $severity->label(), $details], (string) $text);

        return $instance ? $item->compileTemplate($text, $instance) : $text;
    }
}
