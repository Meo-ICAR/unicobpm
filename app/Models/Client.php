<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Client extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $client) {
            if (empty($client->company_id) && auth()->check()) {
                $client->company_id = auth()->user()->profile?->company_id;
            }
        });
    }

    protected $connection = 'proforma';

    protected $table = 'proforma.clients';

    protected $fillable = [
        'company_id',
        'is_person',
        'name',
        'first_name',
        'tax_code',
        'vat_number',
        'email',
        'phone',
        'is_pep',
        'client_type_id',
        'is_sanctioned',
        'is_remote_interaction',
        'general_consent_at',
        'privacy_policy_read_at',
        'consent_special_categories_at',
        'consent_sic_at',
        'consent_marketing_at',
        'consent_profiling_at',
        'status',
        'is_company',
        'is_lead',
        'leadsource_id',
        'acquired_at',
        'contoCOGE',
        'privacy_consent',
        'is_client',
        'subfornitori',
        'is_requiredApprovation',
        'is_approved',
        'is_anonymous',
        'blacklist_at',
        'blacklisted_by',
        'salary',
        'salary_quote',
        'is_art108',
    ];

    protected $casts = [
        'is_person' => 'boolean',
        'is_pep' => 'boolean',
        'is_sanctioned' => 'boolean',
        'is_remote_interaction' => 'boolean',
        'is_company' => 'boolean',
        'is_lead' => 'boolean',
        'privacy_consent' => 'boolean',
        'is_client' => 'boolean',
        'is_requiredApprovation' => 'boolean',
        'is_approved' => 'boolean',
        'is_anonymous' => 'boolean',
        'is_art108' => 'boolean',
        'general_consent_at' => 'datetime',
        'privacy_policy_read_at' => 'datetime',
        'consent_special_categories_at' => 'datetime',
        'consent_sic_at' => 'datetime',
        'consent_marketing_at' => 'datetime',
        'consent_profiling_at' => 'datetime',
        'acquired_at' => 'datetime',
        'blacklist_at' => 'datetime',
        'salary' => 'decimal:2',
        'salary_quote' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function clientType(): BelongsTo
    {
        return $this->belongsTo(ClientType::class);
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'leadsource_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Client::class, 'leadsource_id');
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
        return $this->morphToMany(BusinessFunction::class, 'member', 'unicobpm.business_function_members')
            ->using(BusinessFunctionMember::class)
            ->withPivot('employee_type_id', 'is_manager')
            ->withTimestamps();
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
}
