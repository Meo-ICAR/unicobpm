<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un singolo messaggio (utente, assistente o esito di una tool call) di una conversazione
 * con un agente NeuronAI, usato come storage per NeuronAI\Chat\History\EloquentChatHistory
 * (vedi SegretariaAiAgent::chatHistory()). Ogni riga eredita l'utente autenticato che l'ha
 * generata, e di default un utente vede solo le proprie conversazioni (o quelle senza
 * proprietario) — la Segretaria AI usa un tool dedicato per cercare esplicitamente tra le
 * conversazioni di tutti gli utenti, bypassando questo scope.
 */
class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    protected $fillable = [
        'thread_id',
        'role',
        'content',
        'meta',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if ($message->user_id === null && auth()->check()) {
                $message->user_id = auth()->id();
            }
        });

        static::addGlobalScope('owned', function (Builder $builder): void {
            $userId = auth()->id();

            $builder->where(function (Builder $query) use ($userId): void {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            });
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Solo il testo leggibile del messaggio (i blocchi di tipo "text"), o null se il
     * messaggio è un passaggio tecnico senza testo (es. una tool call o il suo risultato)
     * da non mostrare in una UI di chat.
     */
    public function textContent(): ?string
    {
        $text = collect($this->content ?? [])
            ->where('type', 'text')
            ->pluck('content')
            ->implode("\n");

        return $text !== '' ? $text : null;
    }
}
