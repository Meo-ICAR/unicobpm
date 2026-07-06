<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessTrigger extends Model
{
    /**
     * I campi che possono essere assegnati massivamente (Mass Assignment).
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'model_class',
        'event_type',
        'process_id',
        'conditions',
        'idle_days',
        'is_active',
    ];

    /**
     * I cast nativi per gli attributi del database.
     * Garantisce che la colonna JSON venga letta e salvata automaticamente come array PHP.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'conditions' => 'array',
        'is_active' => 'boolean',
        'idle_days' => 'integer',
    ];

    /**
     * Relazione: Il workflow (Process) che questo trigger deve avviare.
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /**
     * Scope locale per filtrare solo i trigger attualmente attivi.
     * Utilizzabile nel codice come: ProcessTrigger::active()->get();
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
