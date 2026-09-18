<?php

namespace Tests\Unit;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_and_meta_are_cast_to_arrays(): void
    {
        $message = ChatMessage::create([
            'thread_id' => 'thread-1',
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => 'ciao', 'meta' => []]],
            'meta' => ['__id' => 'msg_1'],
        ]);

        $this->assertIsArray($message->fresh()->content);
        $this->assertIsArray($message->fresh()->meta);
        $this->assertSame('ciao', $message->fresh()->content[0]['content']);
    }

    public function test_creating_attributes_the_message_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $message = ChatMessage::create([
            'thread_id' => 'thread-1',
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => 'ciao']],
        ]);

        $this->assertSame($user->id, $message->user_id);
    }

    public function test_a_user_only_sees_their_own_messages_by_default(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        ChatMessage::create(['thread_id' => 't-owner', 'role' => 'user', 'content' => [], 'user_id' => $owner->id]);
        ChatMessage::create(['thread_id' => 't-other', 'role' => 'user', 'content' => [], 'user_id' => $other->id]);

        $this->actingAs($owner);

        $this->assertSame(1, ChatMessage::count());
        $this->assertSame('t-owner', ChatMessage::first()->thread_id);
    }

    public function test_without_the_owned_scope_all_users_messages_are_visible(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        ChatMessage::create(['thread_id' => 't-owner', 'role' => 'user', 'content' => [], 'user_id' => $owner->id]);
        ChatMessage::create(['thread_id' => 't-other', 'role' => 'user', 'content' => [], 'user_id' => $other->id]);

        $this->actingAs($owner);

        $this->assertSame(2, ChatMessage::withoutGlobalScope('owned')->count());
    }

    public function test_text_content_extracts_only_text_blocks(): void
    {
        $withText = new ChatMessage(['content' => [['type' => 'text', 'content' => 'domanda utente']]]);
        $this->assertSame('domanda utente', $withText->textContent());

        $toolCallOnly = new ChatMessage(['content' => []]);
        $this->assertNull($toolCallOnly->textContent());
    }
}
