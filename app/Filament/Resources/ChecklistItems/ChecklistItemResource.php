<?php

namespace App\Filament\Resources\ChecklistItems;

use App\Filament\Resources\ChecklistItems\Pages\CreateChecklistItem;
use App\Filament\Resources\ChecklistItems\Pages\EditChecklistItem;
use App\Filament\Resources\ChecklistItems\Pages\ListChecklistItems;
use App\Filament\Resources\ChecklistItems\Schemas\ChecklistItemForm;
use App\Filament\Resources\ChecklistItems\Tables\ChecklistItemsTable;
use App\Filament\Traits\HasFormSubheading;
use App\Filament\Traits\HasPlanAccess;
use App\Models\ChecklistItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ChecklistItemResource extends Resource
{
    use HasFormSubheading;
    use HasPlanAccess;

    protected static ?string $model = ChecklistItem::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'BPM';

    protected static ?int $navigationSort = 6;

    protected static ?string $label = 'Voce questionario';

    protected static ?string $pluralLabel = 'Voci questionario';

    protected static ?string $recordTitleAttribute = 'label';

    public static function getSubheading(): ?string
    {
        return 'Voci del questionario, con ordine e regole di scrittura sul soggetto.';
    }

    public static function form(Schema $schema): Schema
    {
        return static::withFormSubheading(ChecklistItemForm::configure($schema));
    }

    public static function table(Table $table): Table
    {
        return ChecklistItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChecklistItems::route('/'),
            'create' => CreateChecklistItem::route('/create'),
            'edit' => EditChecklistItem::route('/{record}/edit'),
        ];
    }
}
