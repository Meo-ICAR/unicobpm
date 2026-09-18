<?php

namespace App\Filament\Pages;

use App\Models\AiActionDraft;
use App\Neuron\ManualAssistantAgent;
use App\Services\EmailSendingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Chat\Messages\UserMessage;
use Throwable;

/**
 * Assistente AI che risponde a domande sull'uso dell'applicazione, indicizzato
 * da `php artisan manual:sync` (vedi ManualAssistantAgent), e può predisporre
 * azioni come l'invio di un'email di sollecito: restano bozze (AiActionDraft)
 * finché l'operatore non le conferma esplicitamente da questa pagina.
 *
 * @property-read Schema $form
 */
class AssistenteAi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected string $view = 'filament.pages.assistente-ai';

    protected static ?string $navigationLabel = 'Assistente AI';

    protected static string|\UnitEnum|null $navigationGroup = 'Documentazione';

    protected static ?string $title = 'Assistente AI';

    protected static ?string $slug = 'assistente-ai';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?string $answer = null;

    public ?string $error = null;

    public ?int $inputTokens = null;

    public ?int $outputTokens = null;

    public ?int $cachedInputTokens = null;

    public Collection $pendingDrafts;

    public function mount(): void
    {
        $this->form->fill();
        $this->loadPendingDrafts();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Textarea::make('prompt')
                        ->label('Chiedi all\'Assistente AI')
                        ->placeholder('Es: come emetto un proforma?')
                        ->rows(3)
                        ->autosize()
                        ->required(),
                ])
                    ->id('assistente-ai-form')
                    ->livewireSubmitHandler('send')
                    ->footer([
                        Actions::make([
                            Action::make('send')
                                ->label('Chiedi')
                                ->icon('heroicon-o-paper-airplane')
                                ->submit('assistente-ai-form'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $this->error = null;
        $this->answer = null;
        $this->inputTokens = null;
        $this->outputTokens = null;
        $this->cachedInputTokens = null;

        $prompt = $this->form->getState()['prompt'] ?? null;

        if (blank($prompt)) {
            return;
        }

        try {
            $reply = ManualAssistantAgent::make()->chat(new UserMessage($prompt))->getMessage();
            $this->answer = (string) $reply->getContent();

            $usage = $reply->getUsage();

            if ($usage instanceof Usage) {
                $this->inputTokens = $usage->inputTokens;
                $this->outputTokens = $usage->outputTokens;
                $this->cachedInputTokens = $usage->cachedInputTokens;
            }
        } catch (Throwable $e) {
            $this->error = "Non riesco a rispondere in questo momento: {$e->getMessage()}";
        }

        $this->loadPendingDrafts();
    }

    /**
     * Invia davvero l'email di una bozza preparata dall'assistente: unico punto in cui
     * un'azione dell'AI ha effetto reale, ed è sempre un click esplicito dell'operatore.
     */
    public function confirmDraft(int $draftId): void
    {
        $draft = $this->ownPendingDraft($draftId);

        if (! $draft) {
            return;
        }

        try {
            app(EmailSendingService::class)->send(
                $draft->payload['to'],
                $draft->payload['subject'],
                $draft->payload['body'],
            );

            $draft->update(['status' => 'sent', 'sent_at' => now()]);

            Notification::make()->title('Email inviata a '.$draft->payload['to'])->success()->send();
        } catch (Throwable $e) {
            $draft->update(['status' => 'failed', 'error' => $e->getMessage()]);

            Notification::make()->title('Invio email fallito')->body($e->getMessage())->danger()->send();
        }

        $this->loadPendingDrafts();
    }

    public function cancelDraft(int $draftId): void
    {
        $this->ownPendingDraft($draftId)?->update(['status' => 'cancelled']);

        $this->loadPendingDrafts();
    }

    protected function ownPendingDraft(int $draftId): ?AiActionDraft
    {
        return AiActionDraft::query()
            ->whereKey($draftId)
            ->where('created_by', auth()->id())
            ->where('status', 'pending')
            ->first();
    }

    protected function loadPendingDrafts(): void
    {
        $this->pendingDrafts = AiActionDraft::query()
            ->where('created_by', auth()->id())
            ->where('status', 'pending')
            ->latest()
            ->get();
    }
}
