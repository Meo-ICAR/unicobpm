<?php

namespace App\Filament\Resources\ApiLogs\Schemas;

use App\Models\ApiLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ApiLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Chiamata')->columns(3)->schema([
                TextEntry::make('created_at')->label('Quando')->dateTime('d/m/Y H:i:s'),
                TextEntry::make('direction')->label('Verso')->badge()->formatStateUsing(fn (string $state): string => $state === ApiLog::IN ? 'Ricevuta' : 'Inviata'),
                TextEntry::make('origin')->label('Fatta da')->placeholder('unicobpm'),
                TextEntry::make('app')->label('Con')->placeholder('—'),
                TextEntry::make('method')->label('Metodo'),
                TextEntry::make('url')->label('Indirizzo')->columnSpan(2)->copyable(),
                TextEntry::make('status_code')->label('Esito')->placeholder('—')->badge()
                    ->color(fn (ApiLog $record): string => $record->isSuccessful() ? 'success' : 'danger'),
                TextEntry::make('duration_ms')->label('Durata')->suffix(' ms')->placeholder('—'),
                TextEntry::make('error')->label('Errore di rete')->placeholder('—'),
                TextEntry::make('correlation_id')->label('Correlazione')->placeholder('—')->copyable(),
                TextEntry::make('event_id')->label('Evento')->placeholder('—')->copyable(),
            ]),
            Section::make('Costo interrogazione AI')->columns(4)->visible(fn (ApiLog $record): bool => $record->ai_cost !== null || $record->ai_model !== null)->schema([
                TextEntry::make('ai_cost')->label('Costo')->formatStateUsing(fn ($state): ?string => ApiLog::formatCost($state))->placeholder('—'),
                TextEntry::make('ai_model')->label('Modello')->placeholder('—'),
                TextEntry::make('ai_input_tokens')->label('Token in ingresso')->numeric()->placeholder('—'),
                TextEntry::make('ai_output_tokens')->label('Token in uscita')->numeric()->placeholder('—'),
            ]),
            Section::make('Richiesta')->collapsible()->schema([
                KeyValueEntry::make('request_headers')->label('Intestazioni')->keyLabel('Nome')->valueLabel('Valore')->placeholder('—'),
                TextEntry::make('request_body')->label('Corpo')->state(fn (ApiLog $record): HtmlString => self::pretty($record->request_body))->html()->placeholder('—'),
            ]),
            Section::make('Risposta')->collapsible()->schema([
                KeyValueEntry::make('response_headers')->label('Intestazioni')->keyLabel('Nome')->valueLabel('Valore')->placeholder('—'),
                TextEntry::make('response_body')->label('Corpo')->state(fn (ApiLog $record): HtmlString => self::pretty($record->response_body))->html()->placeholder('—'),
            ]),
            Section::make('Cosa ha attivato')->description('Chiamate collegate, evento registrato, processi avviati e task, in ordine di tempo.')->schema([
                TextEntry::make('timeline')->hiddenLabel()->bulleted()->listWithLineBreaks()
                    ->state(fn (ApiLog $record): array => $record->timeline()->map(fn (array $step): string => $step[0]->format('d/m/Y H:i:s').' — '.$step[1])->all()),
            ]),
        ]);
    }

    private static function pretty(?string $body): HtmlString
    {
        if (blank($body)) {
            return new HtmlString('—');
        }

        $decoded = json_decode($body, true);
        $text = $decoded === null ? $body : json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new HtmlString('<pre style="white-space:pre-wrap;font-size:.8rem">'.e($text).'</pre>');
    }
}
