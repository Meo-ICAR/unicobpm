<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    use HasFactory;

    protected $connection = 'mysql_unicooam';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function companyModules(): HasMany
    {
        return $this->hasMany(CompanyModule::class);
    }
}
