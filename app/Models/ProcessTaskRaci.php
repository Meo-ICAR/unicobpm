<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessTaskRaci extends Model
{
    protected $table = 'process_task_raci';

    protected $fillable = [
        'process_task_id',
        'business_function_id',
        'raci_role',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProcessTask::class, 'process_task_id');
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }
}
