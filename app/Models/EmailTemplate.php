<?php

namespace App\Models;

use App\Enums\Severity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Unico\Core\Models\EmailTemplate as CoreEmailTemplate;

class EmailTemplate extends CoreEmailTemplate
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
        'severity' => Severity::class,
    ];
}
