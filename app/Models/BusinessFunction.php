<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Unico\Core\Models\BusinessFunction as CoreBusinessFunction;

/**
 * Le funzioni aziendali e i loro membri stanno nel database condiviso (connessione `core`, gestite da unico-core): qui si
 * aggiungono le relazioni con i modelli dell'app (Employee, Client, EmployeeType) e con la matrice RACI dei processi.
 */
class BusinessFunction extends CoreBusinessFunction
{
    protected $fillable = [
        'name',
        'code',
        'macro_area',
        'type',
        'description',
        'outsourcable_status',
        'managed_by_code',
        'mission',
        'responsibility',
        'email',
    ];

    public function employees(): MorphToMany
    {
        return $this->morphedByMany(Employee::class, 'member', \App\Models\BusinessFunctionMember::qualifiedTable())
            ->using(BusinessFunctionMember::class)
            ->withPivot('employee_type_id', 'is_manager')
            ->withTimestamps();
    }

    /**
     * Consulenti (Client) a cui è esternalizzato un ruolo di questa funzione aziendale.
     */
    public function clients(): MorphToMany
    {
        return $this->morphedByMany(Client::class, 'member', \App\Models\BusinessFunctionMember::qualifiedTable())
            ->using(BusinessFunctionMember::class)
            ->withPivot('employee_type_id', 'is_manager')
            ->withTimestamps();
    }

    /**
     * Ruoli (EmployeeType) assegnati a questa funzione aziendale, tramite i member
     * (Employee o Client, quando il ruolo è esternalizzato) di business_function_members.
     */
    public function employeeTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            EmployeeType::class,
            \App\Models\BusinessFunctionMember::qualifiedTable(),
            'business_function_id',
            'employee_type_id'
        )->withTimestamps();
    }

    public function raciRoles(): HasMany
    {
        return $this->hasMany(ProcessTaskRaci::class);
    }

    /**
     * Utenti applicativi (account di login) appartenenti a questa funzione aziendale,
     * sia essi dipendenti (Employee) o clienti/mediatori (Client).
     */
    /**
     * Indirizzi email dei membri della funzione che ricoprono il ruolo (nome di EmployeeType) indicato: dipendenti e consulenti esterni,
     * con l'email dell'anagrafica (non serve un account di login). Vuoto se il ruolo non esiste o nessuno lo ricopre qui.
     *
     * @return Collection<int, string>
     */
    public function emailsForRole(string $role): Collection
    {
        $typeId = EmployeeType::query()->where('name', $role)->value('id');

        if ($typeId === null) {
            return collect();
        }

        return $this->employees()->wherePivot('employee_type_id', $typeId)->pluck('email')
            ->merge($this->clients()->wherePivot('employee_type_id', $typeId)->pluck('email'))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();
    }

    public function loginUsers(): Collection
    {
        return $this->employees()->with('loginUser')->get()->pluck('loginUser')
            ->merge($this->clients()->with('user')->get()->pluck('user'))
            ->filter()
            ->values();
    }
}
