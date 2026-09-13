<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checklist extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('ordine');
    }

    /**
     * I task del motore BPM che, per una loro azione di tipo 'fill_checklist', richiedono
     * la compilazione di questa checklist.
     */
    public function processTaskItems(): HasMany
    {
        return $this->hasMany(ProcessTaskItem::class);
    }
}
