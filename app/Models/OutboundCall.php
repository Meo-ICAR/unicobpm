<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una chiamata HTTP in uscita (verso un'altra app o un webhook) con stato, tentativi ed esito. */
class OutboundCall extends Model
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'meta' => 'array', 'next_attempt_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function isWebhook(): bool
    {
        return $this->app === null;
    }
}
