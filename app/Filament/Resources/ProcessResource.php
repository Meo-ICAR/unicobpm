<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcessResource\Pages;
use App\Filament\Resources\ProcessResource\RelationManagers\ProcessTasksRelationManager;
use App\Models\Process;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProcessResource extends Resource
{
    protected static ?string $model = Process::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'BPM';
    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Processo';
    protected static ?string $pluralLabel = 'Processi';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([

            Section::make('Identificazione')
                ->columns(3)
                ->schema([
                    TextInput::make('code')
                        ->required()
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
                ]),

            Section::make('Descrizione')
                ->schema([
                    Textarea::make('description')
                        ->nullable()
                        ->columnSpanFull(),
                ]),

            Section::make('Target & Trigger')
                ->columns(3)
                ->schema([
                    TextInput::make('target_model')
                        ->nullable()
                        ->placeholder('App\\Models\\Customer')
                        ->columnSpanFull(),
                    KeyValue::make('trigger_filters')
                        ->nullable()
                        ->keyLabel('Chiave')
                        ->valueLabel('Valore')
                        ->columnSpanFull(),
                    TextInput::make('trigger_field')
                        ->nullable(),
                    TextInput::make('trigger_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('trigger_value')
                        ->nullable(),
                ]),

            Section::make('Esclusione Condizionale')
                ->columns(3)
                ->schema([
                    TextInput::make('exclude_field')
                        ->nullable(),
                    TextInput::make('exclude_state')
                        ->nullable()
                        ->placeholder('filled | empty | equals'),
                    TextInput::make('exclude_value')
                        ->nullable(),
                ]),

            Section::make('Periodicità')
                ->columns(2)
                ->schema([
                    Toggle::make('is_periodic')
                        ->live()
                        ->inline(false),
                    TextInput::make('cron_expression')
                        ->nullable()
                        ->hidden(fn (Get $get) => ! $get('is_periodic'))
                        ->required(fn (Get $get) => (bool) $get('is_periodic'))
                        ->placeholder('0 1 10 * *')
                        ->helperText('Formato cron standard: minuto ora giorno mese giorno-settimana'),
                ]),

            Section::make('Schedulazione')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('last_activated_at')
                        ->disabled()
                        ->nullable(),
                    DateTimePicker::make('next_run_at')
                        ->disabled()
                        ->nullable(),
                ]),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable()
                    ->label('Attivo'),
                Tables\Columns\IconColumn::make('is_periodic')
                    ->boolean()
                    ->sortable()
                    ->label('Periodico'),
                Tables\Columns\TextColumn::make('next_run_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->label('Prossima Esecuzione')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Stato')
                    ->trueLabel('Solo attivi')
                    ->falseLabel('Solo disattivati'),
                Tables\Filters\TernaryFilter::make('is_periodic')
                    ->label('Periodicità')
                    ->trueLabel('Solo periodici'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code');
    }

    public static function getRelations(): array
    {
        return [
            ProcessTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProcesses::route('/'),
            'create' => Pages\CreateProcess::route('/create'),
            'edit'   => Pages\EditProcess::route('/{record}/edit'),
        ];
    }
}
