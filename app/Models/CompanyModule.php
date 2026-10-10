<?php

namespace App\Models;

use App\Enums\PlanType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\CompanyModule as CoreCompanyModule;

class CompanyModule extends CoreCompanyModule
{
    use HasFactory;

    protected $casts = [
        'plan_type' => PlanType::class,
        'trial_ends_at' => 'date',
        'one_time_cost' => 'decimal:2',
        'monthly_cost' => 'decimal:2',
        'last_invoice_at' => 'date',
        'last_payment_at' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
