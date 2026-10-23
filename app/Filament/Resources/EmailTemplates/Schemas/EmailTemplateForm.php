<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use App\Enums\Severity;
use App\Models\Company;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Unico\Core\Enums\MailType;

class EmailTemplateForm
{
    /**
     * Identificativi delle app (email_templates.app_identifier): sono quelli con cui ogni app cerca i suoi template.
     *
     * @var array<string, string>
     */
    public const APPS = [
        'unicobpm' => 'UnicoBPM',
        'unicooam' => 'UnicoOAM',
        'unicofinance' => 'UnicoFinance',
        'unicocoge' => 'UnicoCoge',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Codice')
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('subject')
                    ->label('Oggetto')
                    ->required(),
                RichEditor::make('body')
                    ->label('Corpo email')
                    // ->required()
                    ->columnSpanFull(),
                TextInput::make('placeholders')
                    ->label('Segnaposto disponibili'),
                Select::make('severity')
                    ->label('Severity')
                    ->options(Severity::options())
                    ->placeholder('Nessuna (template non legato a un check)')
                    ->helperText('Per i check esterni: il template con questo codice e questa severity viene inviato quando il check risponde con tale grado.'),
                Toggle::make('is_active')
                    ->label('Attivo')
                    ->required(),
                Select::make('company_id')
                    ->label('Company')
                    ->options(fn (): array => Company::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->placeholder('Tutte le company')
                    ->helperText('Con una company il template vale solo per le sue pratiche e vince su quello comune.'),
                Select::make('app_identifier')
                    ->label('Applicazione')
                    ->options(self::APPS)
                    ->placeholder('Tutte le applicazioni')
                    ->helperText('Con un\'applicazione il template è riservato a lei e vince su quello comune.'),
                Select::make('type')
                    ->label('Tipo di email')
                    ->options(collect(MailType::cases())->mapWithKeys(fn (MailType $type): array => [$type->value => ucfirst($type->value)])->all())
                    ->placeholder('Qualsiasi')
                    ->helperText('Sceglie anche la casella mittente della company (processo, sollecito, generale).'),
            ]);
    }
}
