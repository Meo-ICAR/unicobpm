<?php

namespace App\Console\Commands;

use App\Mail\ReminderDocumentsMail;
use App\Models\ProcessInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendProcessReminders extends Command
{
    protected $signature = 'bpm:send-reminders';

    protected $description = 'Invia solleciti automatici per i task documentali pendenti';

    public function handle()
    {
        /**
         * PROCEDURA DI INVIO SOLLECITI DOCUMENTALI:
         * Questo comando viene eseguito pianificato per sollecitare i soggetti esterni a completare i propri task documentali pendenti.
         * Nello specifico:
         * 1. Seleziona le istanze di processo attive ('in_progress') bloccate su un task che prevede solleciti attivi ('has_reminders').
         * 2. Per ciascuna pratica, effettua controlli sui limiti configurati:
         *    - Non deve aver superato la soglia 'max_reminders' specificata nel task.
         *    - Devono essere trascorsi almeno 'reminder_interval_days' giorni dall'ultimo sollecito inviato.
         * 3. Se idonea, genera una URL firmata e temporanea (Magic Link) per consentire l'upload sicuro senza autenticazione.
         * 4. Invia l'email tramite il mailer dedicato ed aggiorna la data di ultimo invio e il contatore dei solleciti inviati.
         */
        // 1. Trova le pratiche attive ferme su task che hanno i solleciti attivi
        $praticheDaSollecitare = ProcessInstance::where('status', 'in_progress')
            ->whereHas('currentTask', function ($query) {
                $query->where('has_reminders', true);
            })
            ->get();

        foreach ($praticheDaSollecitare as $pratica) {
            $task = $pratica->currentTask;

            // Controlla se abbiamo raggiunto il limite massimo di solleciti
            if ($pratica->reminders_sent_count >= $task->max_reminders) {
                // Opzionale: Notifica l'ufficio competente che l'agente non risponde
                // $this->notifyInternalStaff($pratica);
                continue;
            }

            // Calcola se sono passati i giorni necessari dall'ultimo invio
            $giorniPassati = now()->diffInDays($pratica->last_reminder_sent_at);

            if ($giorniPassati >= $task->reminder_interval_days) {
                // Rigenera il link magico
                $magicLink = URL::temporarySignedRoute(
                    'public.process.upload',
                    now()->addDays(7),
                    ['instance' => $pratica->id]
                );

                // Invia l'email di sollecito
                Mail::to($pratica->subject->email)->send(new ReminderDocumentsMail($pratica, $magicLink));

                // Aggiorna i contatori della pratica
                $pratica->update([
                    'last_reminder_sent_at' => now(),
                    'reminders_sent_count' => $pratica->reminders_sent_count + 1,
                ]);

                $this->info("Sollecito inviato per la pratica ID: {$pratica->id}");
            }
        }
    }
}
