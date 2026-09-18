<?php

namespace Tests\Feature;

use App\Filament\Pages\AssistenteAi;
use App\Models\AiActionDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'assistente AI non invia mai un'email da solo: PrepareReminderEmailTool crea solo una
 * bozza (AiActionDraft) pending, e solo un click esplicito dell'operatore su questa pagina
 * la trasforma in un invio reale. Qui si testa il click di conferma/annullamento; la
 * creazione della bozza da parte del tool (che risolve un Fornitore sul DB esterno condiviso)
 * non è coperta da test automatici, per lo stesso motivo per cui Fornitore/Document non
 * vengono mai toccati dalla suite (vedi ProcessEligibleRecordsCountTest).
 */
class AssistenteAiDraftConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_a_pending_draft_sends_it_and_marks_it_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $draft = $this->makeDraft($user->id);

        Livewire::test(AssistenteAi::class)
            ->call('confirmDraft', $draft->id);

        $draft->refresh();
        $this->assertSame('sent', $draft->status);
        $this->assertNotNull($draft->sent_at);
    }

    public function test_cancelling_a_pending_draft_leaves_it_unsent(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $draft = $this->makeDraft($user->id);

        Livewire::test(AssistenteAi::class)
            ->call('cancelDraft', $draft->id);

        $draft->refresh();
        $this->assertSame('cancelled', $draft->status);
        $this->assertNull($draft->sent_at);
    }

    public function test_cannot_confirm_another_users_draft(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $draft = $this->makeDraft($owner->id);

        $this->actingAs($intruder);

        Livewire::test(AssistenteAi::class)
            ->call('confirmDraft', $draft->id);

        $draft->refresh();
        $this->assertSame('pending', $draft->status);
        $this->assertNull($draft->sent_at);
    }

    private function makeDraft(int $ownerId): AiActionDraft
    {
        return AiActionDraft::create([
            'type' => 'send_email',
            'payload' => [
                'to' => 'fornitore@example.test',
                'to_name' => 'Fornitore Esempio',
                'subject' => 'Sollecito documentazione',
                'body' => 'Gentile Fornitore, siamo in attesa della documentazione mancante.',
            ],
            'status' => 'pending',
            'created_by' => $ownerId,
        ]);
    }
}
