<?php

namespace App\Filament\Traits;

use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

/**
 * Inietta il getSubheading() della Resource in cima a form/infolist a tutta larghezza.
 */
trait HasFormSubheading
{
    public static function withFormSubheading(Schema $schema): Schema
    {
        if (! method_exists(static::class, 'getSubheading')) {
            return $schema;
        }

        $text = static::getSubheading();

        if (! filled($text)) {
            return $schema;
        }

        return $schema->components([
            Text::make($text)->columnSpanFull(),
            ...array_values($schema->getComponents(withActions: false, withHidden: true)),
        ]);
    }
}
