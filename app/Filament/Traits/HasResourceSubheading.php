<?php

namespace App\Filament\Traits;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Mostra il sottotitolo definito sulla Resource (metodo getSubheading) sotto il titolo della pagina.
 */
trait HasResourceSubheading
{
    public function getSubheading(): string|Htmlable|null
    {
        $resource = static::getResource();

        if (! method_exists($resource, 'getSubheading')) {
            return null;
        }

        return $resource::getSubheading();
    }
}
