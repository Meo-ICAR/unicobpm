<?php

namespace App\Filament\Resources\ProcessEmailReplies\Pages;

use App\Filament\Resources\ProcessEmailReplies\ProcessEmailReplyResource;
use App\Filament\Traits\HasResourceSubheading;
use Filament\Resources\Pages\ListRecords;

class ListProcessEmailReplies extends ListRecords
{
    use HasResourceSubheading;

    protected static string $resource = ProcessEmailReplyResource::class;
}
