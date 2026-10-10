<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Unico\Core\Models\Module as CoreModule;

class Module extends CoreModule
{
    use HasFactory;

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function companyModules(): HasMany
    {
        return $this->hasMany(CompanyModule::class);
    }
}
