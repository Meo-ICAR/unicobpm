<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\VectorStore\FileVectorStore;
use NeuronAI\RAG\VectorStore\VectorStoreInterface;
use RuntimeException;

/**
 * Configurazione condivisa dagli agenti RAG di UnicoBPM (provider Anthropic, embeddings
 * Gemini, indice vettoriale del manuale operativo, elenco tabelle interrogabili via SQL):
 * un solo indice sincronizzato da `php artisan manual:sync`, così l'Assistente Manuale e la
 * Segretaria AI attingono sempre alla stessa base di conoscenza, pur avendo tool e system
 * prompt diversi.
 */
trait HasBpmKnowledgeBase
{
    /**
     * Tabelle interrogabili via SQL: solo dati di dominio (processi, task, checklist, RACI,
     * aziende). Escluse deliberatamente le tabelle di autenticazione/infrastruttura (users,
     * password_reset_tokens, sessions, socialite_users, activity_log, cache, jobs, migrations,
     * chat_messages) per evitare che l'assistente possa leggere credenziali, token, log o
     * conversazioni altrui tramite SQL grezzo (la Segretaria ha un tool dedicato e più
     * controllato per le conversazioni precedenti).
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
}
