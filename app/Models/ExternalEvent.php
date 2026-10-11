<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Evento comunicato da un'altra app: registrato una volta sola, con l'esito (quanti processi ha avviato). */
class ExternalEvent extends Model
{
    public const RECEIVED = 'received';

    public const PROCESSED = 'processed';

    public const IGNORED = 'ignored';

    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'occurred_at' => 'datetime'];
    }
}
