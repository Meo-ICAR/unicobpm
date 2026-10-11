<?php

namespace App\Events;

use App\Models\OutboundCall;
use Illuminate\Foundation\Events\Dispatchable;

class OutboundCallSucceeded
{
    use Dispatchable;

    public function __construct(public readonly OutboundCall $call) {}
}
