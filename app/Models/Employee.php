<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Unico\Core\Models\Employee as CoreEmployee;

class Employee extends CoreEmployee
{
    use HasFactory, SoftDeletes;

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $casts = [
        'is_structure' => 'boolean',
        'is_ghost' => 'boolean',
        'oam_at' => 'date',
        'oam_dismissed_at' => 'date',
        'hiring_date' => 'date',
        'termination_date' => 'date',
    ];

    /**
     * Relazione: Tenant Azienda
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relazione: Account di Login
     */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'profile');
    }

    public function profile(): MorphTo
    {
        // Cerca automaticamente i campi profile_type e profile_id nella tabella users
        return $this->morphTo();
    }

    /**
     * Relazione: Filiale/Sede assegnata
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Relazione Gerarchica: Il mio Responsabile diretto
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'coordinated_by_id');
    }

    /**
     * Relazione Gerarchica: Le persone che coordino (il mio Team)
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'coordinated_by_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function businessFunctions(): MorphToMany
    {
        return $this->morphToMany(BusinessFunction::class, 'member', \App\Models\BusinessFunctionMember::qualifiedTable())
            ->withPivot('is_manager')
            ->withTimestamps();
    }
}
