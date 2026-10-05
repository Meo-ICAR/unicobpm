<?php

namespace App\Models;

use App\Enums\PlanType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyModule extends Model
{
    use HasFactory;

    protected $connection = 'mysql_unicooam';

    protected $fillable = [
        'company_id',
        'module_id',
        'plan_type',
        'trial_ends_at',
        'one_time_cost',
        'monthly_cost',
        'billing_frequency',
        'last_invoice_at',
        'last_payment_at',
    ];

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
