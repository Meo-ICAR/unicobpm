<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stato applicativo di un processo (es. «07 Documenti preliminari attesi»): più fine delle fasi (passi) e dello stato tecnico della pratica
 * (`process_instances.status`). Ogni stato appartiene a una fase; gli stati finali e di eccezione (sospeso) non si cambiano in automatico.
 */
class ProcessState extends Model
{
    protected $fillable = ['process_id', 'process_task_id', 'code', 'name', 'owner', 'ordine', 'is_final', 'is_exception', 'description'];

    protected function casts(): array
    {
        return ['ordine' => 'integer', 'is_final' => 'boolean', 'is_exception' => 'boolean'];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /** Fase (passo) del processo a cui lo stato appartiene. */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class, 'process_task_id');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class, 'process_state_id');
    }

    /** «07 – Documenti preliminari attesi» */
    public function label(): string
    {
        return $this->code.' – '.$this->name;
    }
}
