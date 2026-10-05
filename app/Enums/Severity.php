<?php

namespace App\Enums;

/**
 * Grado di severity restituito da un applicativo esterno per un check, dal meno al più grave.
 */
enum Severity: string
{
    case Ok = 'ok';
    case Regular = 'regular';
    case Warning = 'warning';
    case Alert = 'alert';

    public function level(): int
    {
        return match ($this) {
            self::Ok => 0,
            self::Regular => 1,
            self::Warning => 2,
            self::Alert => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Ok',
            self::Regular => 'Regular',
            self::Warning => 'Warning',
            self::Alert => 'Alert',
        };
    }

    /**
     * Ruoli RACI che ricevono l'email: più il grado è alto, più si allarga la platea.
     * Per 'ok' non si scrive a nessuno.
     *
     * @return array<int, string>
     */
    public function raciRolesToNotify(): array
    {
        return match ($this) {
            self::Ok => [],
            self::Regular => ['R'],
            self::Warning => ['R', 'A'],
            self::Alert => ['R', 'A', 'C'],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
