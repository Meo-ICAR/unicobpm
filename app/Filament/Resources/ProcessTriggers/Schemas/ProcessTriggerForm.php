<?php

namespace App\Filament\Resources\ProcessTriggers\Schemas;

use App\Models\Process;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class ProcessTriggerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // --- SEZIONE SINISTRA: CONFIGURAZIONE CORE ---
                Section::make('Configurazione Workflow')
                    ->description('Definisci cosa far partire e su quale entità.')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([

                                Select::make('process_id')
                                    ->label('Workflow da avviare')
                                    ->options(Process::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live() // Rende il campo reattivo per scatenare l'aggiornamento
                                    ->hint('Il processo che verrà istanziato.')

            // AUTOMAZIONE: Quando cambia il processo, cerchiamo il suo target_model
                                    ->afterStateUpdated(function ($state, $set) {
                                        if (! $state) {
                                            return;
                                        }

                                        $process = Process::find($state);

                                        // target_model su Process è un alias del morphMap (es. 'fornitore'), ma
                                        // model_class qui deve essere il nome di classe completo: HasBpmTriggers
                                        // e BpmSchedulerCommand confrontano questo campo con get_class($model)/class_exists().
                                        if ($process && $process->target_model) {
                                            $set('model_class', Relation::getMorphedModel($process->target_model) ?? $process->target_model);
                                        }
                                    }),

                                Select::make('model_class')
                                    ->label('Modello Monitorato')
                                    ->options(function () {
                                        // Recupera l'array del morphMap dal ServiceProvider. Il valore salvato è il
                                        // nome di classe completo (non l'alias), per essere confrontabile con
                                        // get_class($model) in HasBpmTriggers e class_exists() in BpmSchedulerCommand.
                                        $morphMap = Relation::morphMap();

                                        return collect($morphMap)->mapWithKeys(function ($className) {
                                            return [$className => Str::afterLast($className, '\\')];
                                        })->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->hint('L\'entità che scatena l\'evento.'),

                            ]),

                        Select::make('event_type')
                            ->label('Evento Scatenante')
                            ->options([
                                'created' => '✨ Creazione Record (Immediato)',
                                'updated' => '🔄 Modifica Stato/Campo (Immediato)',
                                'idle' => '⏳ Inattività / Sollecito (Dopo X giorni)',
                            ])
                            ->required()
                            ->live() // Rende il form reattivo al cambio di questa select
                            ->native(false),

                        // Compare solo se il tipo è "idle"
                        TextInput::make('idle_days')
                            ->label('Giorni di tolleranza')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('giorni di fermo')
                            ->required()
                            ->visible(fn (Get $get) => $get('event_type') === 'idle')
                            ->helperText('Il processo partirà se il record non viene modificato per questo numero di giorni.'),
                    ]),

                // --- SEZIONE DESTRA: STATO E INFO ---
                Section::make('Stato Automazione')
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Automazione Attiva')
                            ->helperText('Se disattivato, questo trigger verrà ignorato dal sistema.')
                            ->default(true)
                            ->onColor('success')
                            ->offColor('danger'),

                        Placeholder::make('info')
                            ->label('Nota')
                            ->content('I trigger di tipo "Inattività" vengono controllati ogni mattina dal sistema (Cron Job).'),
                    ]),

                // --- SEZIONE INFERIORE: CONDIZIONI FILTRO ---
                Section::make('Filtri e Condizioni')
                    ->description('Aggiungi regole specifiche (es: avvia solo se lo stato è "Sospeso").')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('conditions')
                            ->label('Regole di validazione')
                            ->schema([
                                TextInput::make('field')
                                    ->label('Nome colonna DB')
                                    ->placeholder('es: status')
                                    ->required(),

                                Select::make('operator')
                                    ->label('Operatore')
                                    ->options([
                                        '=' => 'Uguale a',
                                        '!=' => 'Diverso da',
                                    ])
                                    ->default('=')
                                    ->required(),

                                TextInput::make('value')
                                    ->label('Valore atteso')
                                    ->placeholder('es: sospeso')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->addActionLabel('Aggiungi condizione specifica')
                            ->collapsible(),
                    ]),
            ]);
    }
}
