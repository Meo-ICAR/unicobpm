<?php

namespace App\Filament\Resources\ProcessTasks\RelationManagers;

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
        return $schema->components([
            TextInput::make('name')
                ->required(),
            TextInput::make('ordine')
                ->numeric()
                ->required()
                ->default(0),
            Select::make('action_type')
                ->options([
                    'document'    => 'Documento',
                    'webhook'     => 'Webhook',
                    'email'       => 'Email',
                    'approval'    => 'Approvazione',
                    'checklist'   => 'Checklist',
                    'manual'      => 'Manuale',
                ])
                ->required(),
            Toggle::make('is_required')
                ->inline(false)
                ->default(true),
            Select::make('document_type_id')
                ->relationship('documentType', 'name')
                ->nullable()
                ->searchable()
                ->preload(),
            TextInput::make('handler_job')
                ->nullable()
                ->placeholder('App\\Jobs\\MyHandlerJob'),
            KeyValue::make('config')
                ->nullable()
                ->keyLabel('Chiave')
                ->valueLabel('Valore')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('ordine')->label('#')->sortable(),
                TextColumn::make('name')->label('Nome')->searchable(),
                TextColumn::make('action_type')->label('Tipo Azione')->badge(),
                IconColumn::make('is_required')->label('Obbligatorio')->boolean(),
                TextColumn::make('documentType.name')->label('Tipo Documento')->placeholder('—'),
                TextColumn::make('handler_job')->label('Job')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordine')
            ->headerActions([CreateAction::make()])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
