<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessFunction extends Model
{
    protected $fillable = [
        'code', 'macro_area', 'name', 'type', 'description',
        'outsourcable_status', 'managed_by_code', 'mission', 'responsibility',
    ];

    /**
     * I task di processo assegnati a questa funzione.
     */
    public function processTasks(): HasMany
    {
        return $this->hasMany(ProcessTask::class);
    }

    /**
     * Le voci di checklist assegnate direttamente a questa funzione aziendale.
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }
}
