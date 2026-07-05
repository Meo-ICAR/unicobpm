<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessTask extends Model
{
    protected $orderBy = 'ordine';

    protected $fillable = [
        'process_id',
        'name',
        'code',
        'description',
        'ordine',
        'business_function_id',
        'trigger_field',
        'trigger_state',
        'trigger_value',
        'exclude_field',
        'exclude_state',
        'exclude_value',
        'condition_field',
        'condition_operator',
        'condition_value',
        'has_reminders',
        'reminder_interval_days',
        'max_reminders',
        'escalation_rules',
    ];

    protected function casts(): array
    {
        return [
            'ordine' => 'integer',
            'has_reminders' => 'boolean',
            'reminder_interval_days' => 'integer',
            'max_reminders' => 'integer',
            'escalation_rules' => 'array', // Converte automaticamente il JSON in array PHP
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    public function processTaskItems(): HasMany
    {
        return $this->hasMany(ProcessTaskItem::class)->orderBy('ordine');
    }

    public function raciAssignments(): HasMany
    {
        return $this->hasMany(ProcessTaskRaci::class, 'process_task_id');
    }
}
