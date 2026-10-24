<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Template dei solleciti dei processi, comuni a tutte le company e riservati a UnicoBPM. Idempotente: riallinea i testi.
 *  - `TASK_ESCALATION` (EscalationNotifier, presa in carico): uno per grado con tono crescente (livello 1 regular, 2 warning, 3 e oltre
 *    alert); segnaposto {pratica} {riferimento} {task} {ore} {livello}.
 *  - `PROCESS_REMINDER` (bpm:send-reminders, documenti mancanti alla controparte); segnaposto {pratica} {task} {documenti} {numero}.
 */
class ProcessEmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            'regular' => [
                'Sollecito: {pratica} attende una presa in carico',
                "La pratica {pratica} è ferma al passo «{task}» da {ore} ore e nessuno l'ha ancora presa in carico.\n\nAccedere a UnicoBPM e premere «Prendi in carico» per avviare la lavorazione.",
            ],
            'warning' => [
                'Secondo sollecito: {pratica} ancora ferma al passo «{task}»',
                "La pratica {pratica} è ancora ferma al passo «{task}» da {ore} ore (sollecito di livello {livello}).\n\nIl ritardo rischia di compromettere i termini del processo: chi coordina l'ufficio è pregato di assegnarla o prenderla in carico oggi stesso.",
            ],
            'alert' => [
                'URGENTE: {pratica} bloccata al passo «{task}» da {ore} ore',
                "La pratica {pratica} è bloccata al passo «{task}» da {ore} ore nonostante i solleciti precedenti (livello {livello}).\n\nÈ necessario un intervento immediato del responsabile: assegnare la pratica o registrare il motivo del blocco in UnicoBPM.",
            ],
        ];

        EmailTemplate::updateOrCreate(['code' => 'PROCESS_REMINDER', 'severity' => null, 'company_id' => null, 'app_identifier' => 'unicobpm'], [
            'name' => 'Sollecito documenti alla controparte',
            'type' => 'processo',
            'subject' => 'Sollecito n. {numero}: documenti mancanti per {pratica}',
            'body' => "Gentile cliente,\n\nper proseguire con la pratica {pratica} (fase «{task}») ci servono ancora questi documenti:\n{documenti}\n\nPuò inviarli rispondendo direttamente a questa email con gli allegati: verranno collegati alla pratica in automatico.\n\nGrazie per la collaborazione.",
            'placeholders' => ['{pratica}', '{task}', '{documenti}', '{numero}'],
            'is_active' => true,
        ]);

        foreach ($templates as $severity => [$subject, $body]) {
            EmailTemplate::updateOrCreate(['code' => 'TASK_ESCALATION', 'severity' => $severity, 'company_id' => null, 'app_identifier' => 'unicobpm'], [
                'name' => 'Sollecito di presa in carico ('.$severity.')',
                'type' => 'processo',
                'subject' => $subject,
                'body' => $body,
                'placeholders' => ['{pratica}', '{riferimento}', '{task}', '{ore}', '{livello}'],
                'is_active' => true,
            ]);
        }
    }
}
