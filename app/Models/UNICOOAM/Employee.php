<?php

namespace App\Models;

use App\Models\ComplaintRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'mysql';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'role_title',
        'cf',
        'email',
        'pec',
        'phone',
        'department',
        'oam',
        'oam_at',
        'oam_name',
        'numero_iscrizione_rui',
        'oam_dismissed_at',
        'ivass',
        'hiring_date',
        'termination_date',
        'branch_id',
        'coordinated_by_id',
        'employee_types',
        'supervisor_type',
        'privacy_role',
        'purpose',
        'data_subjects',
        'data_categories',
        'retention_period',
        'extra_eu_transfer',
        'security_measures',
        'privacy_data',
        'is_structure',
        'is_ghost',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
