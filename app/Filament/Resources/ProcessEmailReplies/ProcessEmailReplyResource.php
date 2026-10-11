<?php

namespace App\Filament\Resources\ProcessEmailReplies;

use App\Filament\Resources\ProcessEmailReplies\Pages\ListProcessEmailReplies;
use App\Filament\Resources\ProcessEmailReplies\Tables\ProcessEmailRepliesTable;
use App\Filament\Traits\HasPlanAccess;
use App\Models\ProcessEmailReply;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use UnitEnum;

/** Risposte email ai processi: quelle poco sicure aspettano qui una decisione, le altre mostrano cosa è stato fatto. */
class ProcessEmailReplyResource extends Resource
{
    use HasPlanAccess;

    protected static ?string $model = ProcessEmailReply::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationLabel = 'Risposte email';

    protected static ?string $modelLabel = 'Risposta email';

    protected static ?string $pluralModelLabel = 'Risposte email';

    protected static UnitEnum|string|null $navigationGroup = 'BPM';

    public static function getNavigationBadge(): ?string
    {
        $count = ProcessEmailReply::query()->where('status', ProcessEmailReply::NEEDS_REVIEW)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return ProcessEmailRepliesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListProcessEmailReplies::route('/')];
    }
}
