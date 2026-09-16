<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

class BusinessFunction extends Model
{
    protected $connection = 'mysql';

    protected $table = 'unicobpm.business_functions';

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
        return $this->morphedByMany(Employee::class, 'member', 'unicobpm.business_function_members')
            ->using(BusinessFunctionMember::class)
            ->withPivot('employee_type_id', 'is_manager')
            ->withTimestamps();
    }

    /**
     * Consulenti (Client) a cui è esternalizzato un ruolo di questa funzione aziendale.
     */
    public function clients(): MorphToMany
    {
        return $this->morphedByMany(Client::class, 'member', 'unicobpm.business_function_members')
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
            'unicobpm.business_function_members',
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
    public function loginUsers(): Collection
    {
        return $this->employees()->with('user')->get()
            ->merge($this->clients()->with('user')->get())
            ->pluck('user')
            ->filter()
            ->values();
    }
}
