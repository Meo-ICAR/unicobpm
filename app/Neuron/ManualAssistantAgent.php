<?php

declare(strict_types=1);

namespace App\Neuron;

use Illuminate\Support\Facades\DB;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\RAG;
use NeuronAI\RAG\VectorStore\FileVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;
use NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit;
use RuntimeException;

/**
 * Assistente AI che risponde a domande sull'uso del motore BPM (dalla
 * documentazione di progetto: CLAUDE.md, BPM-DOMAIN-SPEC.md, manuale operativo
 * BPM, indicizzati da `php artisan manual:sync`) e a domande sui dati
 * operativi (es. task in scadenza, stato di un processo) tramite sola lettura
 * del database, limitata alle tabelle di dominio in QUERYABLE_TABLES.
 *
 * Può anche predisporre azioni verso l'esterno (es. un'email di sollecito a un
 * Fornitore), ma solo come bozza: l'invio richiede sempre la conferma di un
 * operatore umano dalla pagina dell'assistente, l'agente non agisce mai da solo.
 */
class ManualAssistantAgent extends RAG
{
    /**
     * Tabelle interrogabili dall'assistente via SQL: solo dati di dominio
     * (processi, task, checklist, RACI, aziende). Escluse deliberatamente le
     * tabelle di autenticazione/infrastruttura (users, password_reset_tokens,
     * sessions, socialite_users, activity_log, cache, jobs, migrations) per
     * evitare che l'assistente possa leggere credenziali, token o log non
     * pertinenti.
     *
     * @var array<int, string>
     */
    protected const QUERYABLE_TABLES = [
        'business_function_members', 'business_functions',
        'checklist_answers', 'checklist_items', 'checklist_submissions', 'checklists',
        'companies',
        'process_instances', 'process_task_executions', 'process_task_item_answers',
        'process_task_items', 'process_task_raci', 'process_tasks', 'process_triggers', 'processes',
    ];

    public static function manualSources(): array
    {
        return [
            base_path('CLAUDE.md'),
            base_path('BPM-DOMAIN-SPEC.md'),
            resource_path('manuals/manuale-operativo-bpm.html'),
        ];
    }

    protected function provider(): AIProviderInterface
    {
        $key = env('ANTHROPIC_API_KEY');

        if (blank($key)) {
            throw new RuntimeException('Chiave API Anthropic mancante: imposta ANTHROPIC_API_KEY nel file .env');
        }

        return new Anthropic(
            key: (string) $key,
            model: (string) env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            max_tokens: 4096,
        );
    }

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
                'Se l\'utente chiede di sollecitare/contattare un Fornitore (es. "invia email a X per sollecito documentazione"), usa lo strumento che prepara la bozza email: non inviare mai un\'email da solo, l\'invio richiede sempre la conferma dell\'operatore dalla pagina.',
                'Se lo strumento segnala più fornitori corrispondenti o nessuno, chiedi chiarimenti all\'utente invece di indovinare a quale fornitore riferirti.',
            ],
            output: [
                'Rispondi in italiano, in modo diretto e operativo.',
            ],
        );
    }

    protected function embeddings(): EmbeddingsProviderInterface
    {
        $baseUri = env('GEMINI_BASE_URL');

        if (blank($baseUri)) {
            throw new RuntimeException('URL del proxy Gemini mancante: imposta GEMINI_BASE_URL nel file .env');
        }

        return new ProxiedGeminiEmbeddingsProvider(
            baseUri: rtrim((string) $baseUri, '/').'/models/',
            key: (string) env('GEMINI_API_KEY', ''),
            model: (string) env('GEMINI_EMBEDDINGS_MODEL', 'gemini-embedding-001'),
        );
    }

    protected function vectorStore(): VectorStoreInterface
    {
        return new FileVectorStore(
            directory: storage_path('app/neuron/manual'),
            name: 'manual',
        );
    }

    protected function tools(): array
    {
        $pdo = DB::connection()->getPdo();

        return [
            CalculatorToolkit::make(),
            ScopedMySQLSchemaTool::make($pdo, self::QUERYABLE_TABLES),
            ScopedMySQLSelectTool::make($pdo, self::QUERYABLE_TABLES),
            PrepareReminderEmailTool::make(),
        ];
    }
}
