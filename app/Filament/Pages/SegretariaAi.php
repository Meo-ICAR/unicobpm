<?php

namespace App\Filament\Pages;

use App\Models\AiActionDraft;
use App\Models\ChatMessage;
use App\Neuron\SegretariaAiAgent;
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
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\UserMessage;
use Throwable;

/**
 * Segretaria AI: a differenza dell'Assistente AI (sola consultazione del manuale, senza
 * memoria), qui la conversazione è a più turni — persistita su ChatMessage per un thread
 * generato a ogni apertura della pagina (vedi SegretariaAiAgent) — e l'agente può proporre
 * azioni (es. bozza email di sollecito): restano bozze (AiActionDraft) finché l'operatore
 * non le conferma esplicitamente da questa pagina, l'AI non invia mai nulla da sola.
 *
 * @property-read Schema $form
 */
class SegretariaAi extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected string $view = 'filament.pages.segretaria-ai';

    protected static ?string $navigationLabel = 'Segretaria AI';

    protected static string|\UnitEnum|null $navigationGroup = 'Documentazione';

    protected static ?string $title = 'Segretaria AI';

    protected static ?string $slug = 'segretaria-ai';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public string $threadId;

    public Collection $messages;

    public ?string $error = null;

    public Collection $pendingDrafts;

    public function mount(): void
    {
        $this->threadId = (string) Str::ulid();
        $this->form->fill();
        $this->loadMessages();
        $this->loadPendingDrafts();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Textarea::make('prompt')
                        ->label('Chiedi o chiedi di fare qualcosa alla Segretaria AI')
                        ->placeholder('Es: invia un\'email al fornitore Rossi per sollecito documentazione')
                        ->rows(3)
                        ->autosize()
                        ->required(),
                ])
                    ->id('segretaria-ai-form')
                    ->livewireSubmitHandler('send')
                    ->footer([
                        Actions::make([
                            Action::make('send')
                                ->label('Invia')
                                ->icon('heroicon-o-paper-airplane')
                                ->submit('segretaria-ai-form'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $this->error = null;

        $prompt = $this->form->getState()['prompt'] ?? null;

        if (blank($prompt)) {
            return;
        }

        try {
            SegretariaAiAgent::make()->setThreadId($this->threadId)->chat(new UserMessage($prompt));
        } catch (Throwable $e) {
            $this->error = "Non riesco a rispondere in questo momento: {$e->getMessage()}";
        }

        $this->form->fill();
        $this->loadMessages();
        $this->loadPendingDrafts();
    }

    /**
     * Invia davvero l'email di una bozza preparata dalla Segretaria: unico punto in cui
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

    protected function loadMessages(): void
    {
        $this->messages = ChatMessage::where('thread_id', $this->threadId)
            ->orderBy('id')
            ->get();
    }
}
