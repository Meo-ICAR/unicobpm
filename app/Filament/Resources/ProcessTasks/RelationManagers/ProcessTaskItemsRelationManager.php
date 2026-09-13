<?php

namespace App\Filament\Resources\ProcessTasks\RelationManagers;

use App\Filament\Resources\ProcessTaskItems\Schemas\ProcessTaskItemForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProcessTaskItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'processTaskItems';

    protected static ?string $title = 'Item del Task';

    public function form(Schema $schema): Schema
    {
        // Stesso vocabolario di action_type dello ProcessTaskItemResource standalone
        // (ProcessTaskItemForm::actionTypeOptions()): sono due interfacce sullo stesso
        // model, devono salvare valori compatibili con quelli letti a runtime dal motore BPM.
        return $schema->components([
            TextInput::make('name')
                ->required(),
            TextInput::make('ordine')
                ->numeric()
                ->required()
                ->default(0),
            Select::make('action_type')
                ->label('Tipo di Azione')
                ->options(ProcessTaskItemForm::actionTypeOptions())
                ->required()
                ->live(),
            Toggle::make('is_required')
                ->label('Obbligatoria')
                ->inline(false)
                ->default(true),
            Select::make('document_type_id')
                ->label('Tipo Documento Richiesto')
                ->relationship('documentType', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get) => $get('action_type') === 'document_upload'),
            Select::make('checklist_id')
                ->label('Checklist da Compilare')
                ->relationship('checklist', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get) => $get('action_type') === 'fill_checklist')
                ->required(fn (Get $get) => $get('action_type') === 'fill_checklist'),
            TextInput::make('handler_job')
                ->label('Job da Eseguire (FQCN)')
                ->nullable()
                ->placeholder('App\\Jobs\\MioJob')
                ->visible(fn (Get $get) => $get('action_type') === 'system_task')
                ->helperText('Classe PHP eseguita in automatico quando la pratica raggiunge questo task (anche di un pacchetto/applicativo esterno installato via composer). Deve avere un costruttore (int $processInstanceId, array $config = []).'),
            KeyValue::make('config')
                ->label('Configurazione')
                ->nullable()
                ->keyLabel('Chiave')
                ->valueLabel('Valore')
                ->columnSpanFull()
                ->visible(fn (Get $get) => in_array($get('action_type'), ['automated_email', 'validation_rule'])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('ordine')->label('#')->sortable(),
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('action_type')
                    ->label('Tipo Azione')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => ProcessTaskItemForm::actionTypeOptions()[$state] ?? $state),
                IconColumn::make('is_required')->label('Obbligatorio')->boolean(),
                TextColumn::make('documentType.name')->label('Tipo Documento')->placeholder('—'),
                TextColumn::make('checklist.name')->label('Checklist')->placeholder('—'),
                TextColumn::make('handler_job')->label('Job')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordine')
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
