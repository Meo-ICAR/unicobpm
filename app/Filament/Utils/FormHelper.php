<?php

namespace App\Filament\Utils;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class FormHelper
{
    /**
     * Etichette leggibili per gli alias registrati in Relation::$morphMap.
     * Un alias non presente qui usa Str::headline($alias) come fallback.
     */
    protected static array $morphLabels = [
        'audit' => 'Audit',
        'branch' => 'Filiale',
        'cliente' => 'Cliente',
        'company' => 'Azienda',
        'complaint' => 'Reclamo',
        'document' => 'Documento',
        'employee' => 'Dipendente',
        'fornitore' => 'Fornitore',
        'website' => 'Sito Web',
    ];

    /**
     * Select per un campo polimorfo (es. Task::taskable, DocumentType::document_typable)
     * che salva l'alias del morph map (Relation::$morphMap), non il nome classe
     * completo: coerente con come questi campi vengono già confrontati altrove
     * (es. Task::getAvailableFor(), filtro 'taskable' in TasksTable).
     */
    public static function polymorphicSelect(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(fn (): array => collect(Relation::$morphMap)
                ->keys()
                ->mapWithKeys(fn (string $alias) => [$alias => static::$morphLabels[$alias] ?? Str::headline($alias)])
                ->sort()
                ->all())
            ->searchable();
    }
}
