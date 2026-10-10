<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Unico\Core\Models\EmployeeTypeResourcePreset as CoreEmployeeTypeResourcePreset;

class EmployeeTypeResourcePreset extends CoreEmployeeTypeResourcePreset
{
    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
