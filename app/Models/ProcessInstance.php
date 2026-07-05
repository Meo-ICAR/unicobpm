<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProcessInstance extends Model
{
    /**
     * La tabella associata al modello.
     */
    protected $table = 'unicobpm.process_instances';

    /**
     * I campi assegnabili in massa (Mass Assignment).
     */
    protected $fillable = [
        'process_id',
        'subject_type',
        'subject_id',
        'current_assignee_type',
        'current_assignee_id',
        'company_id',
        'status',
        'current_task_id',
        'completed_at',
        'last_reminder_sent_at',
        'reminders_sent_count',
    ];

    /**
     * Cast automatico dei tipi di dato.
     */
    protected $casts = [
        'status' => 'string', // enum ('pending','in_progress','completed','rejected','cancelled')
        'reminders_sent_count' => 'integer',
        'completed_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
    ];

    // =========================================================================
    // RELAZIONI ELOQUENT
    // =========================================================================

    /**
     * Il processo (Template) di riferimento per questa istanza.
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * Lo step/task attualmente attivo nella pratica.
     */
    public function currentTask(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class, 'current_task_id');
    }

    /**
     * Il soggetto polimorfo su cui si sta eseguendo il processo
     * (es: un particolare Dipendente, un Fornitore, un Cliente, ecc.)
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * L'assegnatario polimorfo attuale che ha preso in carico il task
     * (es: App\Models\Employee o App\Models\Consultant che stanno lavorando la pratica)
     */
    public function currentAssignee(): MorphTo
    {
        return $this->morphTo();
    }

    // =========================================================================
    // SCOPES LOCALI (MOTORE DYNAMIC RACI)
    // =========================================================================

    /**
     * Scope: Filtra le pratiche "Da prendere in carico" dall'utente loggato.
     * Cerca i task non ancora assegnati dove l'ufficio dell'utente è marchiato come 'R' (Responsible).
     */
    public function scopeWhereCanBeClaimedBy(Builder $query, Model $user): Builder
    {
        // Recuperiamo gli ID delle Business Function a cui l'utente appartiene tramite la pivot polimorfica
        $userBusinessFunctionIds = $user->businessFunctions()->pluck('business_functions.id')->toArray();

        return $query->where('status', 'in_progress')
            ->whereNull('current_assignee_id') // Libera, non ancora reclamata
            ->whereHas('currentTask.raciAssignments', function (Builder $subQuery) use ($userBusinessFunctionIds) {
                $subQuery->where('raci_role', 'R') // Ruolo: Responsible
                    ->whereIn('business_function_id', $userBusinessFunctionIds);
            });
    }

    /**
     * Scope: Filtra le pratiche in cui l'utente (o meglio, la sua Business Function)
     * ha il ruolo di supervisione/approvazione 'A' (Accountable) per lo step corrente.
     */
    public function scopeWhereUserIsAccountable(Builder $query, Model $user): Builder
    {
        $userBusinessFunctionIds = $user->businessFunctions()->pluck('business_functions.id')->toArray();

        return $query->where('status', 'in_progress')
            ->whereHas('currentTask.raciAssignments', function (Builder $subQuery) use ($userBusinessFunctionIds) {
                $subQuery->where('raci_role', 'A') // Ruolo: Accountable
                    ->whereIn('business_function_id', $userBusinessFunctionIds);
            });
    }

    /**
     * Scope: Filtra le pratiche in cui l'utente deve essere solo informato ('I').
     * Comodo se vuoi creare una tab "Pratiche da Osservare".
     */
    public function scopeWhereUserIsInformed(Builder $query, Model $user): Builder
    {
        $userBusinessFunctionIds = $user->businessFunctions()->pluck('business_functions.id')->toArray();

        return $query->whereHas('currentTask.raciAssignments', function (Builder $subQuery) use ($userBusinessFunctionIds) {
            $subQuery->where('raci_role', 'I') // Ruolo: Informed
                ->whereIn('business_function_id', $userBusinessFunctionIds);
        });
    }

    /**
     * Tutte le risposte salvate finora per questa specifica pratica.
     */
    public function checklistAnswers()
    {
        return $this->hasMany(ChecklistAnswer::class, 'process_instance_id');
    }

    // =========================================================================
    // RELAZIONI VERSO LE ESECUZIONI DEI TASK
    // =========================================================================

    /**
     * Tutte le esecuzioni dei task associate a questa pratica.
     * Rappresenta l'intero ciclo di vita e la cronologia dei passaggi.
     */
    public function taskExecutions(): HasMany
    {
        return $this->hasMany(ProcessTaskExecution::class, 'process_instance_id');
    }

    /**
     * L'esecuzione del task attualmente attivo e in corso di lavorazione.
     * Filtra l'esecuzione associata al task corrente che non è ancora stata completata.
     */
    public function currentTaskExecution(): HasOne
    {
        return $this->hasOne(ProcessTaskExecution::class, 'process_instance_id')
            ->where('process_task_id', $this->current_task_id)
            ->whereNull('completed_at');
    }

    /**
     * Scope per filtrare solo le pratiche che l'utente può prendere in carico (RACI - Responsible).
     */
    public function scopeInCodaPerUtente(Builder $query, Model $user): Builder
    {
        return $query->where('status', 'in_progress')
            ->whereNull('current_assignee_id') // Devono essere libere in coda
            ->whereHas('currentTask.raciAssignments', function ($q) use ($user) {
                // Modifica questa logica in base a come associ la RACI (ruoli, reparti, permessi)
                // Esempio: Il task richiede una specifica funzione aziendale che l'utente possiede
                $q->where('role_type', 'responsible')
                    ->whereIn('business_function_id', $user->business_functions->pluck('id'));

                // Oppure se usi Spatie Permission:
                // ->whereIn('permission_name', $user->getAllPermissions()->pluck('name'));
            });
    }

    /**
     * Helper per verificare al volo se un singolo record è prendibile dall'utente
     */
    public function canBeClaimedBy(Model $user): bool
    {
        if ($this->status !== 'in_progress' || ! is_null($this->current_assignee_id)) {
            return false;
        }

        // Ripete la stessa logica dello scope per il singolo record
        return $this->currentTask?->raciAssignments()
            ->where('role_type', 'responsible')
            ->whereIn('business_function_id', $user->business_functions->pluck('id'))
            ->exists() ?? false;
    }
}
