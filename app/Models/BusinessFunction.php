<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
            ->withPivot('is_manager')
            ->withTimestamps();
    }

    public function clients(): MorphToMany
    {
        return $this->morphedByMany(Client::class, 'member', 'unicobpm.business_function_members')
            ->withPivot('is_manager')
            ->withTimestamps();
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
