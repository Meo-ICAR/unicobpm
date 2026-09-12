<?php

namespace Tests\Feature;

use App\Services\SsoTokenBroker;
use Tests\TestCase;

class SsoTokenApiControllerTest extends TestCase
{
    public function test_verifies_a_token_issued_for_the_same_email(): void
    {
        $token = app(SsoTokenBroker::class)->issueToken('user@example.com');

        $response = $this->postJson('/api/verify-token', ['token' => $token, 'email' => 'user@example.com']);

        $response->assertOk();
        $response->assertJson(['valid' => true]);
    }

    public function test_rejects_an_unknown_token(): void
    {
        $response = $this->postJson('/api/verify-token', ['token' => 'bogus', 'email' => 'user@example.com']);

        $response->assertOk();
        $response->assertJson(['valid' => false]);
    }

    public function test_requires_token_and_email(): void
    {
        $response = $this->postJson('/api/verify-token', []);

        $response->assertStatus(422);
    }
}
