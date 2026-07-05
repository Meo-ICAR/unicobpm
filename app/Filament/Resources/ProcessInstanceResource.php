<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcessInstanceResource\Pages;
use App\Models\ProcessInstance;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProcessInstanceResource extends Resource
{
    protected static ?string $model = ProcessInstance::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';
    protected static string|\UnitEnum|null $navigationGroup = 'BPM';
    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Istanza di Processo';
    protected static ?string $pluralLabel = 'Istanze di Processo';

    // Sola lettura — nessun form necessario
    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('process.name')
                    ->label('Processo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending'     => 'gray',
                        'in_progress' => 'info',
                        'completed'   => 'success',
                        'rejected'    => 'danger',
                        'cancelled'   => 'warning',
                        default       => 'gray',
                    }),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Tipo Soggetto')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ultimo_log')
                    ->label('Ultimo Log')
                    ->getStateUsing(function (ProcessInstance $record): string {
                        try {
                            $lastLog = $record->logs()->latest()->first();
                            return $lastLog?->message ?? '';
                        } catch (\Throwable $e) {
                            return '';
                        }
                    })
                    ->placeholder('—')
                    ->wrap()
                    ->limit(80),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completata il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'pending'     => 'In Attesa',
                        'in_progress' => 'In Lavorazione',
                        'completed'   => 'Completata',
                        'rejected'    => 'Respinta',
                        'cancelled'   => 'Annullata',
                    ]),
                Tables\Filters\SelectFilter::make('process_id')
                    ->label('Processo')
                    ->relationship('process', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessInstances::route('/'),
        ];
    }
}
