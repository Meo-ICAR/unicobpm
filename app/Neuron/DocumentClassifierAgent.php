<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\Agent\Agent;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use RuntimeException;

/**
 * Agente mono-scopo (nessun RAG/vector store): riceve un allegato (PDF o immagine) più un
 * elenco di tipi documento candidati e restituisce quale candidato corrisponde, se corrisponde.
 * Usato da App\Services\DocumentClassifier come fallback quando il matching per regex non è
 * risolutivo.
 */
class DocumentClassifierAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        $key = env('ANTHROPIC_API_KEY');

        if (blank($key)) {
            throw new RuntimeException('Chiave API Anthropic mancante: imposta ANTHROPIC_API_KEY nel file .env');
        }

        return new Anthropic(
            key: (string) $key,
            model: (string) env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            max_tokens: 1024,
        );
    }

    protected function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'Sei un classificatore documentale per il motore BPM di UnicoBPM.',
                'Ricevi un file allegato (PDF o immagine) e un elenco di tipi documento candidati, ciascuno con una descrizione di come riconoscerlo.',
            ],
            steps: [
                'Guarda il contenuto reale del file, non solo il nome, per capire di che documento si tratta.',
                'Scegli al massimo un candidato tra quelli elencati: se nessuno corrisponde chiaramente, restituisci item_id null e confidence bassa.',
                'Non inventare mai un item_id che non è tra quelli elencati nel messaggio.',
            ],
            output: [
                'Rispondi esclusivamente nella struttura dati richiesta.',
            ],
        );
    }
}
