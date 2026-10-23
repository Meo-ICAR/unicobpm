<?php

namespace App\Filament\Utils;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rende ordinabili le colonne di una tabella e aggiunge un filtro per ogni colonna che ne è priva:
 * sì/no per le icone e gli interruttori, intervallo per le date, elenco dei valori presenti per i badge,
 * testo contenuto per le altre (anche attraverso le relazioni, come `process.name`).
 */
final class ColumnFilters
{
    /**
     * @param  list<string>  $except  Colonne calcolate (senza un campo nel database): né ordinamento né filtro.
     */
    public static function apply(Table $table, array $except = []): Table
    {
        $existing = $table->getFilters(true);
        $filters = [];

        foreach ($table->getColumns() as $name => $column) {
            $name = (string) $name;

            if (in_array($name, $except, true)) {
                continue;
            }

            if (! $column->isSortable()) {
                $column->sortable();
            }

            $key = self::filterKey($name);

            if (isset($existing[$name]) || isset($existing[$key]) || str_ends_with($name, '_count')) {
                continue;
            }

            $filter = self::filterFor($table, $name, $key, $column);

            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        return $table->pushFilters($filters);
    }

    private static function filterKey(string $name): string
    {
        return 'col_'.str_replace('.', '__', $name);
    }

    private static function filterFor(Table $table, string $name, string $key, Column $column): ?BaseFilter
    {
        $label = (string) $column->getLabel();
        $isRelation = str_contains($name, '.');

        if (! $isRelation && ($column instanceof IconColumn || $column instanceof ToggleColumn)) {
            return TernaryFilter::make($name)->label($label)->placeholder('Tutti');
        }

        if (! $isRelation && preg_match('/(_at|_date|date)$/', $name) === 1) {
            return Filter::make($key)
                ->label($label)
                ->schema([
                    DatePicker::make('dal')->label($label.' dal'),
                    DatePicker::make('al')->label($label.' al'),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['dal'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($name, '>=', $date))
                    ->when($data['al'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($name, '<=', $date)))
                ->indicateUsing(fn (array $data): array => array_values(array_filter([
                    filled($data['dal'] ?? null) ? $label.' dal '.$data['dal'] : null,
                    filled($data['al'] ?? null) ? $label.' al '.$data['al'] : null,
                ])));
        }

        if (! $isRelation && $column instanceof TextColumn && $column->isBadge()) {
            return SelectFilter::make($name)
                ->label($label)
                ->options(fn (): array => $table->getQuery()->getModel()->newQuery()->toBase()
                    ->whereNotNull($name)->where($name, '!=', '')->distinct()->orderBy($name)->pluck($name, $name)
                    ->mapWithKeys(fn (mixed $value, mixed $key): array => [(string) $key => (string) $value])->all());
        }

        return Filter::make($key)
            ->label($label)
            ->schema([TextInput::make('value')->label($label)])
            ->query(function (Builder $query, array $data) use ($name, $isRelation): Builder {
                $value = $data['value'] ?? null;

                if (blank($value)) {
                    return $query;
                }

                if (! $isRelation) {
                    return $query->where($name, 'like', "%{$value}%");
                }

                $relation = substr($name, 0, (int) strrpos($name, '.'));
                $attribute = substr($name, (int) strrpos($name, '.') + 1);

                return $query->whereHas($relation, fn (Builder $related): Builder => $related->where($attribute, 'like', "%{$value}%"));
            })
            ->indicateUsing(fn (array $data): ?string => filled($data['value'] ?? null) ? $label.': '.$data['value'] : null);
    }
}
