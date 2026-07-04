<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class BusinessFunction extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    public function employers(): MorphToMany
    {
        return $this->morphedByMany(Employer::class, 'member', 'business_function_members')
            ->withPivot('is_manager')
            ->withTimestamps();
    }

    public function consultants(): MorphToMany
    {
        return $this->morphedByMany(Consultant::class, 'member', 'business_function_members')
            ->withPivot('is_manager')
            ->withTimestamps();
    }

    public function raciRoles(): HasMany
    {
        return $this->hasMany(ProcessTaskRaci::class);
    }
}
