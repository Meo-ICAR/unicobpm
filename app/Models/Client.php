<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Unico\Core\Models\Client as CoreClient;

/**
 * Client del mediatore (persone fisiche e giuridiche, con dati KYC e consensi privacy).
 * Legge e scrive sulla connessione 'core' -> database unico_core_test.
 */
class Client extends CoreClient
{
    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $client) {
            if (empty($client->company_id) && auth()->check()) {
                $client->company_id = auth()->user()->profile?->company_id;
            }
        });
    }

    /**
     * Aggiunge un ruolo (nome EmployeeType) a employee_roles se non è già presente.
     */
    public function ensureEmployeeRole(string $roleName): void
    {
        $roles = collect(Arr::wrap($this->employee_roles))
            ->filter(fn ($role) => filled($role))
            ->values();

        if ($roles->containsStrict($roleName)) {
            return;
        }

        $this->forceFill([
            'employee_roles' => $roles->push($roleName)->all(),
        ])->save();
    }

    /**
     * Get all addresses for the client.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Funzioni aziendali per cui questo cliente opera come consulente esterno,
     * ossia ricopre un ruolo (EmployeeType) normalmente svolto da un dipendente.
     */
    public function businessFunctions(): MorphToMany
    {
        return $this->morphToMany(BusinessFunction::class, 'member', \App\Models\BusinessFunctionMember::qualifiedTable())
            ->using(BusinessFunctionMember::class)
            ->withPivot('employee_type_id', 'is_manager')
            ->withTimestamps();
    }

    public function profile(): MorphTo
    {
        // Cerca automaticamente i campi profile_type e profile_id nella tabella users
        return $this->morphTo();
    }
}
