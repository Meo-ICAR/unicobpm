<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Unico\Core\Models\BusinessFunctionMember as CoreBusinessFunctionMember;

class BusinessFunctionMember extends CoreBusinessFunctionMember
{
    /**
     * I campi che possono essere assegnati in massa (Mass Assignment).
     */
    protected $fillable = [
        'business_function_id',
        'employee_type_id',
        'member_type',
        'member_id',
        'is_manager',
    ];

    /**
     * Cast automatico dei tipi di dato.
     */
    protected $casts = [
        'is_manager' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $member): void {
            $member->syncClientEmployeeRole();
        });
    }

    /**
     * Se il member è un Client (consulente esterno), propaga il ruolo della
     * funzione in clients.employee_roles così AccessChecker e Filament restano allineati.
     */
    public function syncClientEmployeeRole(): void
    {
        if (! $this->employee_type_id || ! $this->isOutsourced()) {
            return;
        }

        $client = $this->relationLoaded('member')
            ? $this->member
            : Client::query()->find($this->member_id);

        if (! $client instanceof Client) {
            return;
        }

        // Sempre dal database: la relazione in memoria può avere ancora il ruolo precedente.
        $roleName = EmployeeType::query()->whereKey($this->employee_type_id)->value('name');

        if (filled($roleName)) {
            $client->ensureEmployeeRole($roleName);
        }
    }

    // ==========================================
    //                 RELAZIONI
    // ==========================================

    /**
     * Relazione verso la Funzione di Business.
     */
    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class, 'business_function_id');
    }

    /**
     * Relazione Polimorfica verso il membro (Employee o Client).
     */
    public function member(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Ruolo (EmployeeType) ricoperto dal member in questa funzione aziendale.
     */
    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class);
    }

    /**
     * Il ruolo è esternalizzato quando il member è un consulente (Client)
     * anziché un dipendente interno (Employee). Si confronta con
     * Client::getMorphClass() perché Client non ha un alias registrato in
     * Relation::$morphMap, quindi member_type contiene il nome classe completo.
     */
    public function isOutsourced(): bool
    {
        return $this->member_type === (new Client)->getMorphClass();
    }

    /**
     * Nome della tabella qualificato con il database condiviso (core): serve alle relazioni che partono da modelli su un'altra
     * connessione, il cui join sul pivot altrimenti cercherebbe la tabella nel database sbagliato.
     */
    public static function qualifiedTable(): string
    {
        return config('database.connections.'.config('unico-core.connection').'.database').'.business_function_members';
    }
}
