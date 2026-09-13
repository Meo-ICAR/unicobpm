<?php

namespace App\Filament\Resources\ChecklistItems\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class ChecklistItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificazione')
                ->columns(2)
                ->schema([
                    Select::make('checklist_id')
                        ->relationship('checklist', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),
                    ...self::identificationFields(),
                ]),

            Section::make('Tipo di Risposta')
                ->columns(2)
                ->schema(self::answerTypeFields()),

            Section::make('Scrittura Automatica sull\'Anagrafica')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->description('Quando l\'operatore risponde a questa domanda, scrivi il risultato direttamente sul record collegato alla pratica.')
                ->schema(self::writeBackFields()),

            Section::make('Esclusione Condizionale')
                ->columns(3)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('exclude_field')->nullable(),
                    TextInput::make('exclude_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('exclude_value')->nullable(),
                ]),

            Section::make('Regole di Knockout')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema(self::knockoutFields()),

            Section::make('Dipendenze')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->description('Mostra questa voce solo se un\'altra domanda ha un certo valore')
                ->schema(self::dependencyFields()),

        ]);
    }

    /**
     * Campi condivisi con ChecklistItemRelationManager (annidato in ChecklistResource), unica
     * fonte di verità per evitare che le due interfacce sullo stesso model divergano di nuovo.
     *
     * @return array<int, Component>
     */
    public static function identificationFields(): array
    {
        return [
            TextInput::make('item_code')
                ->nullable()
                ->unique(ignoreRecord: true)
                ->placeholder('ES-001'),
            TextInput::make('ordine')
                ->numeric()
                ->default(0)
                ->required(),
            TextInput::make('name')
                ->nullable(),
            TextInput::make('label')
                ->nullable(),
            Textarea::make('question')
                ->nullable()
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function answerTypeFields(): array
    {
        return [
            Select::make('type')
                ->options([
                    'boolean' => 'Sì / No',
                    'text' => 'Testo libero',
                    'number' => 'Numero',
                    'date' => 'Data',
                    'select' => 'Selezione singola',
                    'multiselect' => 'Selezione multipla',
                ])
                ->default('boolean')
                ->required()
                ->live(),
            Toggle::make('is_required')
                ->label('Risposta obbligatoria')
                ->default(true)
                ->inline(false),
            KeyValue::make('options')
                ->nullable()
                ->keyLabel('Chiave')
                ->valueLabel('Etichetta')
                ->columnSpanFull()
                ->hidden(fn (Get $get) => ! in_array($get('type'), ['select', 'multiselect']))
                ->helperText('Opzioni disponibili per il tipo select/multiselect'),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function writeBackFields(): array
    {
        return [
            Select::make('trigger_model')
                ->label('Solo se il soggetto della pratica è di tipo')
                ->options(function () {
                    $morphMap = Relation::morphMap();

                    return collect($morphMap)->mapWithKeys(fn ($className) => [
                        $className => Str::afterLast($className, '\\'),
                    ])->toArray();
                })
                ->searchable()
                ->nullable()
                ->placeholder('Qualsiasi (nessun controllo)')
                ->helperText('Guardia opzionale: se impostata, la scrittura avviene solo se il soggetto della pratica è di questo tipo.'),
            TextInput::make('trigger_field')
                ->label('Campo da Scrivere')
                ->nullable()
                ->helperText('Nome della colonna sul soggetto della pratica in cui salvare il risultato.'),
            Toggle::make('is_timestamp_update')
                ->label('Scrivi la data/ora attuale')
                ->helperText('Se attivo, scrive Carbon::now() nel campo sopra invece del valore della risposta (es. "verificato il ...").')
                ->inline(false)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function knockoutFields(): array
    {
        return [
            Toggle::make('is_knockout')
                ->label('Abilita knockout')
                ->helperText('Se la risposta corrisponde al valore knockout, la pratica passa a "rejected"')
                ->inline(false)
                ->live(),
            TextInput::make('knockout_value')
                ->nullable()
                ->hidden(fn (Get $get) => ! $get('is_knockout')),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function dependencyFields(): array
    {
        return [
            TextInput::make('depends_on_code')
                ->nullable()
                ->placeholder('item_code della domanda padre'),
            TextInput::make('depends_on_value')
                ->nullable()
                ->placeholder('Valore atteso'),
        ];
    }
}
