<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Unico\Core\Models\Client as CoreClient;

/**
 * Client del mediatore (persone fisiche e giuridiche, con dati KYC e consensi privacy).
 * Legge e scrive sulla connessione 'core' -> database unicosuite.
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
     * Id dei ruoli che l'esterno ricopre (ruoli esternalizzati, es. un consulente), dai nomi in `employee_roles`.
     *
     * @return list<int>
     */
    public function employeeTypeIds(): array
    {
        return EmployeeType::query()->whereIn('name', Arr::wrap($this->employee_roles))->pluck('id')->all();
    }

    public function profile(): MorphTo
    {
        // Cerca automaticamente i campi profile_type e profile_id nella tabella users
        return $this->morphTo();
    }
}
