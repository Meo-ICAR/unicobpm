<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Unico\Core\Models\EmployeeType as CoreEmployeeType;

/**
 * Ruolo e insieme ufficio dei processi: la matrice RACI e i passi puntano al ruolo, e ne fanno parte i dipendenti e gli esterni
 * (client, per i ruoli esternalizzabili) che lo hanno in `employee_roles`.
 */
class EmployeeType extends CoreEmployeeType
{
    /**
     * Codice della vecchia funzione aziendale e nome del ruolo che la sostituisce. Le funzioni senza un ruolo corrispondente
     * hanno ricevuto un ruolo nuovo con questo nome.
     *
     * @var array<string, string>
     */
    public const OFFICE_ROLES = [
        'COMPLIANCE' => 'compliance',
        'HR' => 'risorse umane',
        'LEGAL' => 'legale',
        'IT' => 'it',
        'BUS-BO' => 'back office',
        'CTRL-AML' => 'antiriciclaggio',
        'BUS-RETE-EXT' => 'rete agenti esterna',
        'CTRL-COMPL' => 'controllo compliance',
        'COGE-CONTABILE' => 'contabile',
        'COGE-CONTABILE-RESP' => 'contabile responsabile',
        'COGE-CONSULENTE' => 'consulente coge',
        'COGE-AMMINISTRATORE' => 'amministratore',
        'SEGRETERIA' => 'segretaria',
        'WHISTLEBLOWING' => 'responsabile whistleblowing',
        'WHISTLEBLOWING-SUPPLENTI' => 'supplente whistleblowing',
        'CDA' => 'cda',
        'AD' => 'amministratore delegato',
        'AFFARI' => 'affari societari e contabilità',
        'ORG-RU' => 'organizzazione e risorse umane',
        'COMMERCIALE' => 'commerciale',
        'SEGR-RETE' => 'segreteria di rete',
        'INT-AUDIT' => 'audit',
        'DPO' => 'dpo',
    ];

    /**
     * Valori di `outsourcable_status`: «yes» e «partial» permettono di assegnare il ruolo anche a un esterno (client).
     *
     * @var array<string, string>
     */
    public const OUTSOURCABLE = [
        'no' => 'No',
        'partial' => 'Parziale',
        'yes' => 'Sì',
    ];

    /**
     * Ruolo dell'ufficio con questo codice: lo crea se manca (nome da OFFICE_ROLES, altrimenti il nome dell'ufficio in minuscolo)
     * e dà il codice a un ruolo che esisteva già con quel nome. Il nome dell'ufficio (es. «Ufficio Compliance») diventa la
     * descrizione se non ce n'è una; gli altri attributi aggiornano il ruolo.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function office(string $code, array $attributes = []): self
    {
        $officeName = (string) ($attributes['name'] ?? $code);
        $roleName = self::OFFICE_ROLES[$code] ?? mb_strtolower($officeName);
        unset($attributes['name']);

        $role = static::query()->where('code', $code)->first()
            ?? static::query()->where('name', $roleName)->first()
            ?? new static(['name' => $roleName]);

        $role->fill(['code' => $code] + $attributes);
        $role->description ??= $officeName;
        $role->save();

        return $role;
    }

    /**
     * Casting dei tipi di attributi (Sintassi moderna Laravel).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_external' => 'boolean',
        ]);
    }

    /** Il ruolo può essere ricoperto anche da un esterno (client). */
    public function isOutsourcable(): bool
    {
        return in_array($this->outsourcable_status, ['yes', 'partial'], true);
    }

    /* =========================================================================
     | RELAZIONI / QUERY ASSOCIATE
     | ========================================================================= */

    /**
     * Restituisce la query per ottenere gli impiegati che hanno questo ruolo.
     * Esegue una ricerca JSON nel campo 'employee_roles' del Model Employee.
     */
    public function employees(): Builder
    {
        return Employee::whereJsonContains('employee_roles', $this->name);
    }

    /** Esterni (client) che ricoprono questo ruolo. */
    public function clients(): Builder
    {
        return Client::whereJsonContains('employee_roles', $this->name);
    }

    /**
     * Account di login di chi ricopre il ruolo, dipendenti ed esterni.
     *
     * @return Collection<int, User>
     */
    public function loginUsers(): Collection
    {
        return $this->employees()->with('loginUser')->get()->pluck('loginUser')
            ->merge($this->clients()->with('user')->get()->pluck('user'))
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Email di chi ricopre il ruolo (dall'anagrafica, non serve un account); se nessuno, l'email dell'ufficio.
     *
     * @return Collection<int, string>
     */
    public function memberEmails(): Collection
    {
        $emails = $this->employees()->pluck('email')
            ->merge($this->clients()->pluck('email'))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        return $emails->isNotEmpty() || ! filter_var($this->email, FILTER_VALIDATE_EMAIL) ? $emails : collect([$this->email]);
    }

    public function raciRoles(): HasMany
    {
        return $this->hasMany(ProcessTaskRaci::class);
    }

    /* =========================================================================
     | SCOPES
     | ========================================================================= */

    /**
     * Scope per filtrare per ruoli esterni o interni.
     */
    public function scopeExternal(Builder $query, bool $isExternal = true): Builder
    {
        return $query->where('is_external', $isExternal);
    }

    /**
     * Scope per filtrare per tipo azienda (es. FINANCE).
     */
    public function scopeForCompanyType(Builder $query, string $companyType): Builder
    {
        return $query->where('companytype', $companyType);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(EmployeeTypePermission::class, 'employee_type_id');
    }

    /**
     * Preset di accesso totale (tutte le azioni CRUD) sulle risorse,
     * affiancati alla matrice granulare di permissions().
     */
    public function resourcePresets(): HasMany
    {
        return $this->hasMany(EmployeeTypeResourcePreset::class, 'employee_type_id');
    }
}
