<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un rinvio concesso per uno step di processo (a chi, quando, fino a quando, da chi). */
class ProcessPostponement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['from_at' => 'datetime', 'to_date' => 'date', 'moved_hard_deadline' => 'boolean'];
    }
}
