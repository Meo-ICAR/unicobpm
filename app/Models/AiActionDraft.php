<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un'azione proposta dall'assistente AI (es. invio di un'email di sollecito) che resta
 * "pending" finché un operatore umano non la conferma esplicitamente dalla pagina
 * dell'assistente: l'AI non esegue mai da sola azioni con effetti verso l'esterno.
 */
class AiActionDraft extends Model
{
    protected $fillable = [
        'type',
        'payload',
        'status',
        'created_by',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
