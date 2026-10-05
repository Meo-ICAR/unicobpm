<?php

namespace Tests\Unit;

use App\Services\SsoTokenBroker;
use Tests\TestCase;

class SsoTokenBrokerTest extends TestCase
{
    public function test_a_freshly_issued_token_verifies_for_the_matching_email(): void
    {
        $broker = new SsoTokenBroker;

        $token = $broker->issueToken('user@example.com');

        $this->assertTrue($broker->verifyAndConsume($token, 'user@example.com'));
    }

    public function test_a_token_cannot_be_used_twice(): void
    {
        $broker = new SsoTokenBroker;

        $token = $broker->issueToken('user@example.com');
        $broker->verifyAndConsume($token, 'user@example.com');

        $this->assertFalse($broker->verifyAndConsume($token, 'user@example.com'));
    }

    public function test_a_token_does_not_verify_for_a_different_email(): void
    {
        $broker = new SsoTokenBroker;

        $token = $broker->issueToken('user@example.com');

        $this->assertFalse($broker->verifyAndConsume($token, 'other@example.com'));
    }

    public function test_an_unknown_token_never_verifies(): void
    {
        $broker = new SsoTokenBroker;

        $this->assertFalse($broker->verifyAndConsume('does-not-exist', 'user@example.com'));
    }
}
