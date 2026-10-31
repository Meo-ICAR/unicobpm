<?php

namespace Database\Seeders;

use App\Enums\Severity;
use App\Models\EmailTemplate;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessTrigger;
use Illuminate\Database\Seeder;

/**
 * IBAN degli agenti che varia. UnicoPEC legge l'IBAN dalle fatture e lo confronta con la scheda dell'agente. Due processi:
 *  1. PRC-IBAN-VARIATO, avviato dall'evento `fornitore.iban_variato` (nome dell'agente, IBAN mascherati, motivo): avvisa subito il contabile e guida
 *     la verifica (richiamata al numero già in anagrafica, documento della banca). Si chiude confermando l'IBAN in UnicoPEC (Settings → IBAN agenti);
 *  2. PRC-IBAN-ALLINEAMENTO, giornaliero: interroga il controllo `fornitori:iban-non-allineati` di UnicoPEC e, finché c'è anche un solo agente non
 *     allineato, scrive ogni giorno al contabile; il grado sale col tempo (regular: contabile; warning, dopo 3 giorni: anche il responsabile;
 *     alert, dopo 7 giorni o con IBAN estero: anche la compliance). Quando tutti sono allineati il controllo torna «ok» e le email finiscono da sole.
 * Nelle email non c'è mai un IBAN intero. Il seeder è idempotente e riallinea i testi.
 */
class IbanAllineamentoProcessSeeder extends Seeder
{
    public function run(): void
    {
        $accountant = EmployeeType::office('COGE-CONTABILE', ['name' => 'Contabile']);
        $manager = EmployeeType::office('COGE-CONTABILE-RESP', ['name' => 'Contabile responsabile']);
        $compliance = EmployeeType::office('COMPLIANCE', ['name' => 'Ufficio Compliance']);
        $admin = EmployeeType::office('COGE-AMMINISTRATORE', ['name' => 'Amministratore']);

        $this->variato($accountant, $manager, $compliance, $admin);
        $this->allineamento($accountant, $manager, $compliance, $admin);
    }

    private function variato(EmployeeType $accountant, EmployeeType $manager, EmployeeType $compliance, EmployeeType $admin): void
    {
        $notice = $this->template('IBAN_VARIATO_AVVISO', 'IBAN variato – avviso al contabile',
            'IBAN variato per {external_data.fornitore}: da verificare prima di pagare',
            "Nelle fatture dell'agente {external_data.fornitore} compare un IBAN diverso da quello in scheda o dalle fatture precedenti.\n\n"
            ."Motivo: {external_data.motivo}\nIBAN nuovo: {external_data.iban_nuovo}\nIBAN in scheda: {external_data.iban_scheda}\nIBAN precedenti: {external_data.iban_precedenti}\n\n"
            ."Prima di usarlo per un pagamento: richiamare l'agente al numero GIÀ in anagrafica (non a quello scritto nell'email), farsi dare il documento della banca "
            ."e solo dopo confermare l'IBAN in UnicoPEC (Settings → IBAN agenti). Finché non è confermato l'IBAN non è verificato e il contabile riceve un avviso ogni giorno.");

        $this->template('IBAN_VARIATO_SOLLECITO', 'IBAN variato – sollecito',
            'Sollecito: IBAN di {riferimento} ancora da verificare ({ore} ore)',
            "La verifica dell'IBAN variato ({riferimento}) è ferma da {ore} ore (sollecito di livello {livello}).\n\nRichiamare l'agente al numero in anagrafica, raccogliere il documento della banca e confermare l'IBAN in UnicoPEC (Settings → IBAN agenti).");

        $process = Process::updateOrCreate(['code' => 'PRC-IBAN-VARIATO'], [
            'name' => 'IBAN agente variato',
            'description' => 'Avvio da UnicoPEC (fornitore.iban_variato): avviso al contabile, richiamata all\'agente, documento della banca, conferma dell\'IBAN.',
            'is_active' => true,
        ]);

        $task = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 10], [
            'name' => 'Verifica dell\'IBAN variato',
            'code' => 'iban-variato-verifica',
            'escalation_rules' => [
                ['level' => 1, 'delay_hours' => 24, 'action' => 'notify_assignee', 'until_claimed' => true, 'email_template_code' => 'IBAN_VARIATO_SOLLECITO'],
                ['level' => 2, 'delay_hours' => 72, 'action' => 'notify_manager', 'until_claimed' => true, 'email_template_code' => 'IBAN_VARIATO_SOLLECITO'],
            ],
        ]);

        $this->raci($task, $accountant, $manager, $compliance, $admin);
        $this->item($task, 10, 'Avviso al contabile', 'automated_email', false, ['target_business_function' => 'COGE-CONTABILE', 'email_template_id' => $notice->id]);
        $this->item($task, 20, 'Richiamata all\'agente al numero già in anagrafica (data, ora, esito)', 'text_input', true);
        $this->item($task, 30, 'Documento della banca che attesta l\'IBAN (estremi)', 'text_input', true);
        $this->item($task, 40, 'IBAN confermato in UnicoPEC (Settings → IBAN agenti)? Indicare chi e quando', 'text_input', true);

        ProcessTrigger::updateOrCreate(['event_type' => 'external', 'external_event' => 'fornitore.iban_variato', 'name' => 'IBAN di un agente variato'], [
            'model_class' => 'external',
            'process_id' => $process->id,
            'is_active' => true,
        ]);
    }

    private function allineamento(EmployeeType $accountant, EmployeeType $manager, EmployeeType $compliance, EmployeeType $admin): void
    {
        $tones = [
            Severity::Regular->value => ['prefix' => 'Segnalazione', 'text' => 'Ci sono agenti il cui IBAN non è ancora allineato.'],
            Severity::Warning->value => ['prefix' => 'Attenzione', 'text' => 'Attenzione: da giorni ci sono agenti con IBAN non allineato. Non usare quegli IBAN per i pagamenti.'],
            Severity::Alert->value => ['prefix' => 'ALLERTA', 'text' => 'ALLERTA: IBAN non allineato da oltre una settimana, oppure IBAN estero variato. Verificare subito e non pagare su quel conto.'],
        ];

        foreach ($tones as $severity => $tone) {
            EmailTemplate::updateOrCreate(['code' => 'IBAN_NON_ALLINEATI', 'severity' => $severity], [
                'name' => 'IBAN agenti non allineati — '.ucfirst($severity),
                'subject' => "{$tone['prefix']} IBAN agenti non allineati ({value})",
                'body' => "{$tone['text']}\n\nAgenti da allineare: {value}\n\nDettaglio (nome, motivo, giorni, ultime 4 cifre dell'IBAN):\n{details}\n\n"
                    ."Per allineare: richiamare l'agente al numero già in anagrafica, raccogliere il documento della banca e confermare l'IBAN in UnicoPEC (Settings → IBAN agenti). "
                    ."Finché c'è anche un solo agente non allineato questa email si ripete ogni giorno.\n\nEmail automatica di UnicoBPM (processo Allineamento IBAN agenti).",
                'placeholders' => ['{value}', '{severity}', '{details}'],
                'is_active' => true,
            ]);
        }

        $process = Process::updateOrCreate(['code' => 'PRC-IBAN-ALLINEAMENTO'], [
            'name' => 'Allineamento IBAN agenti',
            'description' => 'Controllo giornaliero su UnicoPEC: finché un agente ha un IBAN diverso o non verificato, il contabile riceve un\'email ogni giorno, con grado crescente.',
            'is_active' => true,
            'is_periodic' => true,
            'recurrence_frequency' => 'daily',
        ]);

        $task = $process->tasks()->updateOrCreate(['process_id' => $process->id, 'ordine' => 10], ['name' => 'IBAN da allineare', 'code' => 'iban-allineamento']);
        $this->raci($task, $accountant, $manager, $compliance, $admin);

        $this->item($task, 10, 'IBAN agenti non allineati', 'external_check', true, [
            'app' => 'unicopec',
            'command' => 'fornitori:iban-non-allineati',
            'email_template_code' => 'IBAN_NON_ALLINEATI',
            // Un'email al giorno finché il problema resta, a ogni grado.
            'repeat_days' => ['regular' => 1, 'warning' => 1, 'alert' => 1],
        ]);
    }

    private function raci($task, EmployeeType $accountant, EmployeeType $manager, EmployeeType $compliance, EmployeeType $admin): void
    {
        $task->raciAssignments()->updateOrCreate(['employee_type_id' => $accountant->id], ['raci_role' => 'R']);
        $task->raciAssignments()->updateOrCreate(['employee_type_id' => $manager->id], ['raci_role' => 'A']);
        $task->raciAssignments()->updateOrCreate(['employee_type_id' => $compliance->id], ['raci_role' => 'C']);
        $task->raciAssignments()->updateOrCreate(['employee_type_id' => $admin->id], ['raci_role' => 'I']);
    }

    private function template(string $code, string $name, string $subject, string $body): EmailTemplate
    {
        return EmailTemplate::updateOrCreate(['code' => $code, 'severity' => null, 'company_id' => null, 'app_identifier' => null], [
            'name' => $name, 'type' => 'processo', 'subject' => $subject, 'body' => $body, 'is_active' => true,
        ]);
    }

    /** @param array<string, mixed>|null $config */
    private function item($task, int $order, string $name, string $type, bool $required = false, ?array $config = null): void
    {
        $task->processTaskItems()->updateOrCreate(['process_task_id' => $task->id, 'ordine' => $order], [
            'name' => $name, 'action_type' => $type, 'is_required' => $required, 'config' => $config,
        ]);
    }
}
