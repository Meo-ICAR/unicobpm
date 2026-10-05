<?php

namespace App\Services;

use App\Enums\Severity;
use App\Models\BusinessFunction;
use App\Models\ProcessInstance;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Esegue un'azione 'external_check': l'applicativo esterno (GET /api/checks/{command}) restituisce
 * solo lo stato del controllo, {"value": ..., "severity": "ok|regular|warning|alert", "details": "..." (opzionale)}; qui si decide
 * cosa farne: si sceglie l'EmailTemplate (code + severity) e si scrive ai ruoli RACI del task
 * padre previsti per quella severity (vedi Severity::raciRolesToNotify()).
 *
 * config dell'item: {"app": "proforma", "command": "clienti:check-missing-piva", "email_template_code": "CHECK_PIVA"}
 */
class ExternalCheckRunner
{
    public function __construct(
        protected ExternalAppResolver $appResolver,
        protected EmailSendingService $emailSender,
    ) {}

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

        ['value' => $value, 'severity' => $severity, 'details' => $details] = $result;

        if ($severity === Severity::Ok) {
            return "Check {$app}/{$command}: valore {$value}, severity ok — nessuna email da inviare.";
        }

        $template = $item->resolveCheckEmailTemplate($severity);

        if (! $template) {
            return "Check {$app}/{$command}: valore {$value}, severity ".($severity?->value ?? 'assente').' — nessun template email trovato.';
        }

        $effectiveSeverity = $severity ?? $template->severity;
        $recipients = $this->recipientsFor($item->task, $effectiveSeverity);

        if ($recipients->isEmpty()) {
            return "Check {$app}/{$command}: valore {$value}, severity {$effectiveSeverity->value} — nessun destinatario da avvisare.";
        }

        $subject = $this->fill($template->subject, $value, $details, $effectiveSeverity, $item, $instance);
        $body = $this->fill($template->body, $value, $details, $effectiveSeverity, $item, $instance);

        $sent = collect();
        $failed = collect();

        foreach ($recipients as $email) {
            try {
                $this->emailSender->send($email, $subject, $body);
                $sent->push($email);
            } catch (\Throwable $e) {
                Log::error("Check {$app}/{$command}: invio email a {$email} fallito.", ['error' => $e->getMessage()]);
                $failed->put($email, $e->getMessage());
            }
        }

        $label = "Check {$app}/{$command}: valore {$value}, severity {$effectiveSeverity->value}";

        $summary = $label.' — '.($sent->isEmpty() ? 'nessuna email inviata' : 'email inviata a '.$sent->implode(', ')).'.';

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
            ->with('businessFunction')
            ->get()
            ->flatMap(fn ($assignment) => $this->emailsOf($assignment->businessFunction))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function emailsOf(?BusinessFunction $function): Collection
    {
        if (! $function) {
            return collect();
        }

        return filled($function->email)
            ? collect([$function->email])
            : $function->loginUsers()->pluck('email');
    }

    /**
     * @return array{value: string, severity: ?Severity, details: string}|null null se l'applicativo non risponde.
     */
    private function fetchStatus(string $app, string $command): ?array
    {
        try {
            $response = Http::asJson()
                ->withHeaders(['X-Api-Key' => (string) config('services.bpm.bridge_api_key')])
                ->timeout(15)
                ->connectTimeout(4)
                ->get("{$this->appResolver->urlFor($app)}/api/checks/{$command}");
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
        ];
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
