<?php

namespace App\Filament\Resources\Checklists\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChecklistItemRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Voci della Checklist';

    protected static bool $shouldRegisterNavigation = false;

    public function form(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Identificazione')
                ->columns(2)
                ->schema([
                    TextInput::make('item_code')
                        ->nullable()
                        ->unique(ignoreRecord: true)
                        ->placeholder('ES-001'),
                    TextInput::make('ordine')
                        ->numeric()
                        ->default(0)
                        ->required(),
                    TextInput::make('name')->nullable(),
                    TextInput::make('label')->nullable(),
                    Textarea::make('question')
                        ->nullable()
                        ->columnSpanFull(),
                ]),

            Section::make('Tipo di Risposta')
                ->columns(2)
                ->schema([
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
                        ->label('Obbligatoria')
                        ->default(true)
                        ->inline(false),
                    KeyValue::make('options')
                        ->nullable()
                        ->keyLabel('Chiave')
                        ->valueLabel('Etichetta')
                        ->columnSpanFull()
                        ->hidden(fn (Get $get) => ! in_array($get('type'), ['select', 'multiselect'])),
                ]),

            Section::make('Regole di Knockout')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('is_knockout')
                        ->label('Abilita knockout')
                        ->inline(false)
                        ->live(),
                    TextInput::make('knockout_value')
                        ->nullable()
                        ->hidden(fn (Get $get) => ! $get('is_knockout')),
                ]),

            Section::make('Dipendenze')
                ->columns(2)
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('depends_on_code')
                        ->nullable()
                        ->placeholder('item_code della domanda padre'),
                    TextInput::make('depends_on_value')
                        ->nullable(),
                ]),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('ordine')->label('#')->sortable(),
                TextColumn::make('item_code')->label('Codice')->placeholder('—'),
                TextColumn::make('label')->label('Voce')->searchable(),
                TextColumn::make('type')->label('Tipo')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'boolean' => 'gray',
                        'text' => 'info',
                        'number' => 'warning',
                        'date' => 'success',
                        'select' => 'primary',
                        'multiselect' => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_required')->label('Obbl.')->boolean(),
                IconColumn::make('is_knockout')->label('KO')->boolean(),
            ])
            ->defaultSort('ordine')
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
