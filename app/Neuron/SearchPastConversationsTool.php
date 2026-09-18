<?php

declare(strict_types=1);

namespace App\Neuron;

use App\Models\ChatMessage;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * Cerca una parola chiave tra le domande poste in passato dagli operatori a qualunque
 * assistente AI (Manuale o Segretaria), di QUALSIASI utente: bypassa deliberatamente lo
 * scope "owned" di ChatMessage (che di norma limita un utente alle proprie conversazioni)
 * perché la Segretaria deve poter dire "un collega ha già chiesto questo" o recuperare
 * contesto lasciato da un altro operatore. Sola lettura, nessuna scrittura.
 */
class SearchPastConversationsTool extends Tool
{
    protected const MAX_RESULTS = 15;

    public function __construct()
    {
        parent::__construct(
            'search_past_conversations',
            'Cerca tra le domande poste in passato dagli operatori (di qualunque utente) agli assistenti AI, per parola chiave. Utile per recuperare contesto già discusso o evitare di richiedere due volte la stessa cosa.'
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'parola_chiave',
                type: PropertyType::STRING,
                description: 'Termine o argomento da cercare nelle domande passate (es. "sollecito documentazione", nome di un fornitore).',
                required: true,
            ),
            new ToolProperty(
                name: 'solo_utente',
                type: PropertyType::STRING,
                description: 'Se valorizzato, limita la ricerca alle conversazioni di un utente il cui nome contiene questo testo.',
                required: false,
            ),
        ];
    }

    /**
     * Quante righe "role=user" più recenti considerare prima di filtrare per parola chiave:
     * il testo vive dentro il JSON "content", quindi il filtro avviene in PHP (via
     * ChatMessage::textContent()) invece che con una sintassi di path JSON specifica del
     * motore DB — resta corretto sia su MySQL sia su SQLite (usato dai test).
     */
    protected const SCAN_WINDOW = 500;

    public function __invoke(string $parola_chiave, ?string $solo_utente = null): string
    {
        $query = ChatMessage::withoutGlobalScope('owned')
            ->where('role', 'user');

        if (filled($solo_utente)) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.$solo_utente.'%'));
        }

        $messages = $query->with('user')
            ->latest()
            ->limit(self::SCAN_WINDOW)
            ->get()
            ->filter(fn (ChatMessage $message) => str_contains(
                mb_strtolower((string) $message->textContent()),
                mb_strtolower($parola_chiave),
            ))
            ->take(self::MAX_RESULTS);

        if ($messages->isEmpty()) {
            return "Nessuna conversazione passata contiene \"{$parola_chiave}\".";
        }

        return $messages
            ->map(function (ChatMessage $message) {
                $utente = $message->user?->name ?? 'utente sconosciuto';
                $data = $message->created_at?->format('d/m/Y H:i');

                return "- [{$data}] {$utente}: ".str($message->textContent())->limit(200);
            })
            ->implode("\n");
    }
}
