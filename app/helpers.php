<?php

use App\Enums\PlanType;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\EmployeeTypePermission;
use App\Models\EmployeeTypeResourcePreset;
use App\Models\Fornitore;
use App\Models\Resource;
use Illuminate\Support\Arr;

if (! function_exists('effectivePlanType')) {
    /**
     * Risolve il piano effettivo dell'installazione.
     *
     * Se PLAN_TYPE è valorizzata nel .env (config('plan.type')), ha sempre
     * la precedenza. Altrimenti si usa il piano risolto al login per
     * l'azienda dell'utente (vedi ResolveCompanyPlanTypeOnLogin), salvato in
     * sessione. In assenza di entrambi, il default resta FULL.
     */
    function effectivePlanType(): PlanType
    {
        $envType = config('plan.type');

        if (! empty($envType)) {
            return PlanType::tryFrom(strtoupper((string) $envType)) ?? PlanType::Full;
        }

        $sessionType = session('plan_type');

        if (! empty($sessionType)) {
            return PlanType::tryFrom(strtoupper((string) $sessionType)) ?? PlanType::Full;
        }

        return PlanType::Full;
    }
}

if (! function_exists('checkPiano')) {
    /**
     * Verifica se una funzionalità è accessibile considerando sia il PIANO che
     * il RUOLO UTENTE (EmployeeType).
     *
     * Il risultato è memoizzato per la durata della richiesta: checkPiano()
     * viene invocato molte volte per ogni render della navigazione Filament
     * (un controllo per ogni azione CRUD di ogni risorsa) e piano/ruolo non
     * cambiano nel corso della stessa richiesta.
     */
    function checkPiano(string $feature, ?string $callerClass = null): bool
    {
        static $cache = [];

        $userId = auth()->id() ?? 'guest';
        // Il valore del piano entra nella chiave cosi' i test che lo cambiano
        // a runtime non leggono un risultato memoizzato sotto un piano diverso.
        $key = $feature.'|'.$userId.'|'.effectivePlanType()->value;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        return $cache[$key] = resolvePianoAccess($feature, $callerClass);
    }
}

if (! function_exists('resolvePianoAccess')) {
    function resolvePianoAccess(string $feature, ?string $callerClass): bool
    {
        // STEP 0: Bypass totale per Admin/SuperAdmin (App\Enums\UserRole),
        // che vedono sempre tutto indipendentemente da piano e ruolo EmployeeType.
        $authUser = auth()->user();

        if ($authUser && ! empty($authUser->role)) {
            $userRole = $authUser->role instanceof UserRole
                ? $authUser->role
                : UserRole::tryFrom($authUser->role);

            if ($userRole === UserRole::ADMIN || $userRole === UserRole::SUPER_ADMIN) {
                return true;
            }
        }

        // STEP 1: Piano / licenza.
        $plan = effectivePlanType();

        if (! $plan->hasFeature($feature, $callerClass)) {
            return false;
        }

        // STEP 2: Ruolo utente, tramite EmployeeType (vedi
        // app/Filament/Resources/EmployeeTypes e EmployeeTypePermission).
        // Se non si riesce a risolvere alcun ruolo per l'utente (non è un
        // Employee, o l'Employee non ha ruoli), il controllo viene saltato
        // e l'accesso resta deciso solo dal piano (STEP 1) — stesso
        // comportamento "fail open" per gli utenti senza ruolo assegnato.
        $employeeTypeIds = resolveUserEmployeeTypeIds(auth()->user());

        if (! empty($employeeTypeIds)) {
            $resourceId = Resource::query()
                ->forCurrentApp()
                ->where('key', $feature)
                ->value('id');

            if (! $resourceId) {
                return true;
            }

            // L'accesso è concesso se il ruolo ha un permesso granulare specifico
            // (EmployeeTypePermission) OPPURE se ha un preset di accesso totale
            // sulla risorsa (EmployeeTypeResourcePreset, gestito da admin/super_admin
            // in EmployeeTypeResource → tab "Preset Procedure").
            $hasGranularPermission = EmployeeTypePermission::query()
                ->whereIn('employee_type_id', $employeeTypeIds)
                ->where('resource_id', $resourceId)
                ->exists();

            $hasPreset = EmployeeTypeResourcePreset::query()
                ->whereIn('employee_type_id', $employeeTypeIds)
                ->where('resource_id', $resourceId)
                ->exists();

            if (! $hasGranularPermission && ! $hasPreset) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('resolveUserEmployeeTypeIds')) {
    /**
     * Risolve gli EmployeeType (ruoli) dell'utente loggato, a partire dal suo
     * profilo Employee, Fornitore o Client (User::profile, morphTo) e dal
     * relativo campo employee_roles (JSON, un profilo può avere più ruoli,
     * stessa struttura per tutti e tre i modelli). Client rappresenta sia
     * consulenti esterni che clienti finali, entrambi possono ricoprire un
     * ruolo EmployeeType. Ritorna un array vuoto per utenti senza profilo
     * Employee/Fornitore/Client o senza ruoli assegnati.
     */
    function resolveUserEmployeeTypeIds(mixed $user): array
    {
        if (! $user) {
            return [];
        }

        $profile = $user->profile ?? null;

        if (! $profile instanceof Employee && ! $profile instanceof Fornitore && ! $profile instanceof Client) {
            return [];
        }

        $roles = $profile->employee_roles;

        if (is_string($roles)) {
            $roles = json_decode($roles, true);
        }

        $roles = Arr::wrap($roles);

        if (empty($roles)) {
            return [];
        }

        return EmployeeType::query()->whereIn('name', $roles)->pluck('id')->all();
    }
}
