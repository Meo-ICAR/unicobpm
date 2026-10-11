<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Destinatario di terzi che riceve gli eventi dei processi (firmati con il suo segreto). */
class WebhookEndpoint extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'events' => 'array', 'is_active' => 'boolean'];
    }

    public function listensTo(string $event): bool
    {
        $events = (array) $this->events;

        return $events === [] || in_array($event, $events, true);
    }
}
