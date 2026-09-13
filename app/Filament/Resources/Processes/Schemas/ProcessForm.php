<?php

namespace App\Filament\Resources\Processes\Schemas;

use App\Models\Process;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class ProcessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificazione')
                ->columns(3)
                ->schema([
                    TextInput::make('code')
                     //   ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50),
                    TextInput::make('name')
                        ->required()
                        ->columnSpan(2),
                    TextInput::make('version')
                        ->numeric()
                        ->required()
                        ->default(1),
                    Toggle::make('is_active')
                        ->default(true)
                        ->inline(false),
                    Textarea::make('description')
                        ->nullable()
                        ->columnSpanFull(),
                ]),

            Section::make('Target & Trigger')
                ->columns(3)
                ->collapsible()
                ->schema([
                    Select::make('target_model')
                        ->label('Modello Principale del Processo (Target)')
                        ->placeholder('Seleziona il modello associato a questo workflow')
                        ->options(function () {
                            // Recupera l'array del morphMap centralizzato nel tuo AppServiceProvider
                            $morphMap = Relation::morphMap();

                            // Trasforma le classi in nomi leggibili per l'interfaccia utente
                            return collect($morphMap)->mapWithKeys(function ($className, $alias) {
                                $readableName = Str::afterLast($className, '\\');

                                return [$alias => $readableName];
                            })->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set) {
                            // I campi trigger_field/exclude_field appartengono alla tabella del
                            // modello precedente: azzerandoli evitiamo di salvare un nome colonna
                            // che non esiste più sul nuovo target_model selezionato.
                            $set('trigger_field', null);
                            $set('exclude_field', null);
                            $set('trigger_filters.status', null);
                        })
                        ->nullable()
                        ->columnSpanFull()
                        ->hint('Indica quale entità aziendale è il soggetto principale di questo tipo di processo.'),
                    Select::make('trigger_filters.status')
                        ->label('Filtro Ricorrenza: Stato del Record')
                        ->options(fn (Get $get) => Process::targetModelStatusValues($get('target_model')))
                        ->searchable()
                        ->nullable()
                        ->disabled(fn (Get $get) => empty(Process::targetModelStatusValues($get('target_model'))))
                        ->placeholder('Nessun filtro (considera tutti i record)')
                        ->columnSpanFull()
                        ->helperText('Usato solo dai processi ricorrenti (sezione Periodicità): quando lo scheduler genera automaticamente le pratiche, considera solo i record con questo valore nel campo "status". Se il modello selezionato non ha un campo "status", questo filtro non è disponibile.'),
                    Select::make('trigger_field')
                        ->label('Campo di Attivazione')
                        ->options(fn (Get $get) => Process::targetModelColumnOptions($get('target_model')))
                        ->searchable()
                        ->nullable()
                        ->disabled(fn (Get $get) => blank($get('target_model')))
                        ->placeholder('Seleziona prima il Modello Target')
                        ->helperText('Il campo del record da controllare per decidere se avviare il processo.'),
                    Select::make('trigger_state')
                        ->label('Condizione')
                        ->options([
                            'filled' => 'È valorizzato',
                            'empty' => 'È vuoto',
                            'equals' => 'È uguale a...',
                        ])
                        ->nullable(),
                    TextInput::make('trigger_value')
                        ->label('Valore di Confronto')
                        ->nullable()
                        ->hidden(fn (Get $get) => $get('trigger_state') !== 'equals'),
                ]),

            Section::make('Esclusione Condizionale')
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Select::make('exclude_field')
                        ->label('Campo di Esclusione')
                        ->options(fn (Get $get) => Process::targetModelColumnOptions($get('target_model')))
                        ->searchable()
                        ->nullable()
                        ->disabled(fn (Get $get) => blank($get('target_model')))
                        ->placeholder('Seleziona prima il Modello Target')
                        ->helperText('Se questo campo soddisfa la condizione, il record viene escluso anche se il trigger sarebbe soddisfatto.'),
                    Select::make('exclude_state')
                        ->label('Condizione')
                        ->options([
                            'filled' => 'È valorizzato',
                            'empty' => 'È vuoto',
                            'equals' => 'È uguale a...',
                        ])
                        ->nullable(),
                    TextInput::make('exclude_value')
                        ->label('Valore di Confronto')
                        ->nullable()
                        ->hidden(fn (Get $get) => $get('exclude_state') !== 'equals'),
                ]),

            Section::make('Record Eleggibili & Reminder')
                ->columns(2)
                ->schema([
                    Placeholder::make('eligible_records_count')
                        ->label('Record Eleggibili Oggi')
                        ->content(fn (?Process $record): string => $record?->eligibleRecordsCount() !== null
                            ? (string) $record->eligibleRecordsCount()
                            : 'Non applicabile (nessun target_model configurato)'),
                    Toggle::make('include_eligible_count_in_reminders')
                        ->label('Includi il conteggio nei reminder')
                        ->helperText("Se attivo, i solleciti inviati all'utente RACI responsabile riportano anche quanti record soddisfano oggi i criteri di questo processo.")
                        ->inline(false),
                ]),

            Section::make('Periodicità')
                ->columns(3)
                ->schema([
                    Toggle::make('is_periodic')
                        ->live()
                        ->inline(false)
                        ->columnSpanFull(),

                    Select::make('cron_unit')
                        ->label('Ripeti ogni')
                        ->options([
                            'minuti' => 'Minuti',
                            'ore' => 'Ore',
                            'giorni' => 'Giorni',
                            'settimana' => 'Settimana (in un giorno preciso)',
                        ])
                        ->live()
                        ->dehydrated(false)
                        ->hidden(fn (Get $get) => ! $get('is_periodic'))
                        ->required(fn (Get $get) => (bool) $get('is_periodic'))
                        ->afterStateHydrated(function (Set $set, Get $get, ?Process $record) {
                            $parsed = Process::parseSimpleCronExpression($record?->cron_expression);
                            $set('cron_unit', $parsed['unit']);
                            $set('cron_value', $parsed['value']);
                        })
                        ->afterStateUpdated(function (Set $set, Get $get) {
                            self::recomputeCronExpression($set, $get);
                        }),

                    TextInput::make('cron_value')
                        ->label(fn (Get $get) => match ($get('cron_unit')) {
                            'settimana' => 'Giorno della settimana',
                            default => 'Ogni quante unità',
                        })
                        ->numeric()
                        ->minValue(fn (Get $get) => $get('cron_unit') === 'settimana' ? 0 : 1)
                        ->maxValue(fn (Get $get) => $get('cron_unit') === 'settimana' ? 6 : 999)
                        ->helperText(fn (Get $get) => $get('cron_unit') === 'settimana'
                            ? '0 = Domenica, 1 = Lunedì, 2 = Martedì ... 6 = Sabato'
                            : null)
                        ->live(onBlur: true)
                        ->dehydrated(false)
                        ->hidden(fn (Get $get) => ! $get('is_periodic'))
                        ->required(fn (Get $get) => (bool) $get('is_periodic'))
                        ->afterStateUpdated(function (Set $set, Get $get) {
                            self::recomputeCronExpression($set, $get);
                        }),

                    TextInput::make('cron_expression')
                        ->label('Espressione cron (calcolata)')
                        ->nullable()
                        ->disabled()
                        ->dehydrated()
                        ->hidden(fn (Get $get) => ! $get('is_periodic'))
                        ->helperText('Generata automaticamente dalla scelta qui sopra: non modificabile a mano.'),
                ]),

            Section::make('Schedulazione')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('last_activated_at')->disabled()->nullable(),
                    DateTimePicker::make('next_run_at')->disabled()->nullable(),
                ]),

        ]);
    }

    /**
     * Ricalcola il campo tecnico cron_expression a partire dalle scelte leggibili
     * (unità + valore numerico) fatte nel form.
     */
    protected static function recomputeCronExpression(Set $set, Get $get): void
    {
        $unit = $get('cron_unit');
        $value = $get('cron_value');

        if (blank($unit) || blank($value) || ! is_numeric($value)) {
            return;
        }

        $set('cron_expression', Process::buildSimpleCronExpression($unit, (int) $value));
    }
}
