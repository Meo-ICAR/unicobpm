<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Unico\Core\Models\EmployeeType as CoreEmployeeType;

/**
 * Ruolo e insieme ufficio dei processi: la matrice RACI e i passi puntano al ruolo, e ne fanno parte i dipendenti e gli esterni
 * (client, per i ruoli esternalizzabili) che lo hanno in `employee_roles`.
 */
class EmployeeType extends CoreEmployeeType
{
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

    /* =========================================================================
     | RELAZIONI / QUERY ASSOCIATE
     | ========================================================================= */

    /**
     * Restituisce la query per ottenere gli impiegati che hanno questo ruolo.
     * Esegue una ricerca JSON nel campo 'employee_roles' del Model Employee.
     */
    public function employees(): Builder
    {
        return Employee::whereJsonContains('employee_roles', $this->name)
            ->where(fn (Builder $active) => $active->whereNull('is_active')->orWhere('is_active', true))
            ->where(fn (Builder $active) => $active->whereNull('termination_date')->orWhereDate('termination_date', '>', now()));
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

    /**
     * Unisce questo ruolo a un altro (stesso ufficio con due nomi): chi lo ricopre passa all'altro, la matrice RACI e i passi
     * puntano all'altro, i dati d'ufficio mancanti passano all'altro, poi questo ruolo si toglie.
     */
    public function mergeInto(self $target): void
    {
        if ($target->is($this)) {
            return;
        }

        self::replaceHolderRole($this->name, $target->name);
        ProcessTaskRaci::query()->where('employee_type_id', $this->id)->get()->each(function (ProcessTaskRaci $assignment) use ($target): void {
            $duplicate = ProcessTaskRaci::query()->where('process_task_id', $assignment->process_task_id)->where('employee_type_id', $target->id)->exists();
            $duplicate ? $assignment->delete() : $assignment->update(['employee_type_id' => $target->id]);
        });
        ProcessTask::query()->where('employee_type_id', $this->id)->update(['employee_type_id' => $target->id]);

        foreach ([$this->permissions(), $this->resourcePresets()] as $relation) {
            $relation->get()->each(function ($row) use ($target): void {
                try {
                    $row->update(['employee_type_id' => $target->id]);
                } catch (UniqueConstraintViolationException) {
                    $row->delete();
                }
            });
        }

        foreach (['macro_area', 'type', 'description', 'managed_by_code', 'mission', 'responsibility', 'email', 'manager_type', 'manager_id'] as $field) {
            $target->{$field} ??= $this->{$field};
        }

        $target->save();
        $this->delete();
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
