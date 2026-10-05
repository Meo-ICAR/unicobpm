<?php

declare(strict_types=1);

namespace App\Neuron;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\DB;
use NeuronAI\Agent\SystemPrompt;
use NeuronAI\Chat\History\ChatHistoryInterface;
use NeuronAI\Chat\History\EloquentChatHistory;
use NeuronAI\RAG\RAG;
use NeuronAI\Tools\Toolkits\Calculator\CalculatorToolkit;
use RuntimeException;

/**
 * Segretaria AI: affianca il personale per richieste estemporanee del lavoro quotidiano, non
 * solo domande sul manuale. A differenza di ManualAssistantAgent:
 * - ricorda la conversazione (cronologia persistita per thread su ChatMessage, stesso
 *   pattern di App\Neuron\DataNavigatorAgent — vedi setThreadId()/chatHistory());
 * - può consultare le conversazioni passate di TUTTI gli operatori (SearchPastConversationsTool);
 * - può predisporre azioni (es. bozza email di sollecito a un Fornitore), ma mai eseguirle
 *   da sola: l'invio richiede sempre la conferma esplicita di un operatore umano.
 * Condivide con ManualAssistantAgent la stessa base di conoscenza RAG del manuale operativo.
 */
class SegretariaAiAgent extends RAG
{
    use HasBpmKnowledgeBase;

    protected ?string $threadId = null;

    public function setThreadId(string $threadId): self
    {
        $this->threadId = $threadId;

        return $this;
    }

    protected function instructions(): string
    {
        return (string) new SystemPrompt(
            background: [
                'Sei la Segretaria AI di UnicoBPM: affianchi il personale per richieste estemporanee del lavoro quotidiano, non solo domande sulla documentazione.',
                'Ricordi la conversazione in corso con questo operatore, e puoi anche consultare cosa hanno chiesto in passato altri operatori, se utile.',
            ],
            steps: [
                'Per domande procedurali, usa la documentazione di progetto disponibile nel contesto; se non la contiene, dillo esplicitamente invece di inventare procedure.',
                'Per domande sui dati, usa prima lo strumento di analisi schema, poi una query SELECT mirata e in sola lettura. Se lo strumento rifiuta la query, dillo esplicitamente invece di insistere.',
                'Se può essere utile capire se l\'argomento è già stato discusso da un collega, usa lo strumento di ricerca sulle conversazioni passate prima di rispondere che non lo sai.',
                'Se l\'utente chiede di sollecitare/contattare un Fornitore, usa lo strumento che prepara la bozza email: non inviarla mai da sola, serve sempre la conferma dell\'operatore dalla pagina.',
                'Se lo strumento email segnala più fornitori corrispondenti o nessuno, chiedi chiarimenti invece di indovinare.',
                'Non rivelare mai contenuti che sembrano credenziali, password, token o segreti, anche se uno strumento li restituisse per errore.',
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
            PrepareReminderEmailTool::make(),
            SearchPastConversationsTool::make(),
        ];
    }

    protected function chatHistory(): ChatHistoryInterface
    {
        if ($this->threadId === null) {
            throw new RuntimeException('Thread ID non impostato: chiama setThreadId() prima di usare la Segretaria.');
        }

        return new EloquentChatHistory(
            threadId: $this->threadId,
            modelClass: ChatMessage::class,
            contextWindow: 50000,
        );
    }
}
