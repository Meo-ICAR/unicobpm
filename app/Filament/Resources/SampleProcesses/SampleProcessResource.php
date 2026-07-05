<?php

namespace App\Filament\Resources\SampleProcesses;

use App\Filament\Resources\SampleProcesses\Pages\CreateSampleProcess;
use App\Filament\Resources\SampleProcesses\Pages\EditSampleProcess;
use App\Filament\Resources\SampleProcesses\Pages\ListSampleProcesses;
use App\Filament\Resources\SampleProcesses\Schemas\SampleProcessForm;
use App\Filament\Resources\SampleProcesses\Tables\SampleProcessesTable;
use App\Models\SampleProcess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SampleProcessResource extends Resource
{
    protected static ?string $model = SampleProcess::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SampleProcessForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SampleProcessesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSampleProcesses::route('/'),
            'create' => CreateSampleProcess::route('/create'),
            'edit' => EditSampleProcess::route('/{record}/edit'),
        ];
    }
}
