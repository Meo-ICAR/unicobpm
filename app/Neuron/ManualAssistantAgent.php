<?php

declare(strict_types=1);

namespace App\Neuron;

use Illuminate\Support\Facades\DB;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\RAG\RAG;
use NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit;

/**
 * Assistente AI di sola consultazione: risponde a domande sull'uso del motore BPM (dalla
 * documentazione di progetto: CLAUDE.md, BPM-DOMAIN-SPEC.md, manuale operativo BPM,
 * indicizzati da `php artisan manual:sync`) e a domande sui dati operativi (es. task in
 * scadenza, stato di un processo) tramite sola lettura del database, limitata alle tabelle
 * di dominio in QUERYABLE_TABLES.
 *
 * Non agisce mai e non ha memoria tra una domanda e l'altra: per richieste estemporanee che
 * richiedono un'azione (es. sollecitare un Fornitore) o che beneficiano di una conversazione
 * a più turni, vedi SegretariaAiAgent.
 */
class ManualAssistantAgent extends RAG
{
    use HasBpmKnowledgeBase;

    protected function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'Sei l\'assistente utente di UnicoBPM, motore di Business Process Management per mediatori creditizi (pratiche/processi, task, RACI, escalation, checklist).',
                'Rispondi alle domande procedurali ("come si fa...") SOLO usando le informazioni recuperate dalla documentazione di progetto (documenti allegati al contesto).',
                'Rispondi alle domande sui dati operativi (es. task in scadenza, stato di un processo, conteggi) interrogando il database con gli strumenti SQL disponibili, in sola lettura.',
            ],
            steps: [
                'Per domande procedurali: se la documentazione non contiene la risposta, dillo esplicitamente invece di inventare procedure.',
                'Per domande sui dati: usa prima lo strumento di analisi schema per capire tabelle e colonne disponibili, poi esegui una query SELECT mirata. Se lo strumento SQL rifiuta la query (tabella non consentita o query di scrittura), dillo esplicitamente all\'utente invece di riprovare all\'infinito.',
                'Non rivelare mai contenuti di colonne che sembrano credenziali, password, token o segreti, anche se una query li restituisse per errore.',
                'Se l\'utente chiede di compiere un\'azione (inviare email, sollecitare qualcuno, aggiornare dati), spiega che per quello serve la Segretaria AI: tu rispondi solo a domande, non agisci.',
            ],
            output: [
                'Rispondi in italiano, in modo diretto e operativo.',
            ],
        );
    }

    protected function tools(): array
    {
        $pdo = DB::connection()->getPdo();

        return [
            CalculatorToolkit::make(),
            ScopedMySQLSchemaTool::make($pdo, self::QUERYABLE_TABLES),
            ScopedMySQLSelectTool::make($pdo, self::QUERYABLE_TABLES),
        ];
    }
}
