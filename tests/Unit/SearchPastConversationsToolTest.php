<?php

namespace Tests\Unit;

use App\Models\ChatMessage;
use App\Models\User;
use App\Neuron\SearchPastConversationsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il punto di questo tool è proprio bypassare lo scope "owned" di ChatMessage: un operatore
 * deve poter trovare cosa hanno chiesto altri colleghi. Qui si verifica che lo faccia
 * correttamente, filtrando solo i messaggi utente testuali.
 */
class SearchPastConversationsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_a_matching_question_asked_by_another_user(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Rossi']);
        $bob = User::factory()->create(['name' => 'Bob Verdi']);

        ChatMessage::create([
            'thread_id' => 't-alice',
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => 'come sollecito la documentazione a un fornitore?']],
            'user_id' => $alice->id,
        ]);

        $this->actingAs($bob);

        $result = (new SearchPastConversationsTool)('documentazione');

        $this->assertStringContainsString('Alice Rossi', $result);
        $this->assertStringContainsString('sollecito la documentazione', $result);
    }

    public function test_filters_by_user_name_when_given(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Rossi']);
        $bob = User::factory()->create(['name' => 'Bob Verdi']);

        ChatMessage::create([
            'thread_id' => 't-alice',
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => 'quante pratiche sono in scadenza?']],
            'user_id' => $alice->id,
        ]);

        ChatMessage::create([
            'thread_id' => 't-bob',
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => 'quante pratiche sono in scadenza?']],
            'user_id' => $bob->id,
        ]);

        $result = (new SearchPastConversationsTool)('scadenza', 'Bob');

        $this->assertStringContainsString('Bob Verdi', $result);
        $this->assertStringNotContainsString('Alice Rossi', $result);
    }

    public function test_ignores_non_text_and_non_user_messages(): void
    {
        $user = User::factory()->create(['name' => 'Alice Rossi']);

        // Messaggio "assistant" di tool_call, senza testo: non è una domanda dell'utente.
        ChatMessage::create([
            'thread_id' => 't-alice',
            'role' => 'assistant',
            'content' => [],
            'user_id' => $user->id,
        ]);

        $result = (new SearchPastConversationsTool)('qualsiasi');

        $this->assertStringContainsString('Nessuna conversazione passata', $result);
    }

    public function test_returns_a_message_when_nothing_matches(): void
    {
        $result = (new SearchPastConversationsTool)('argomento mai discusso da nessuno');

        $this->assertStringContainsString('Nessuna conversazione passata', $result);
    }
}
