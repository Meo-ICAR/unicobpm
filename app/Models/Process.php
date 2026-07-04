<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Process extends Model
{
    protected $fillable = [
        'name',
        'code',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProcessTask::class)->orderBy('ordine');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ProcessInstance::class);
    }
}
