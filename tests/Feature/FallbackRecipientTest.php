<?php

namespace Tests\Feature;

use App\Services\FallbackRecipient;
use Tests\TestCase;

class FallbackRecipientTest extends TestCase
{
    public function test_the_administrator_is_the_fallback_only_when_configured_with_a_valid_address(): void
    {
        config(['services.fallback_recipient' => 'sergio.bracale@races.it']);
        $this->assertSame(['sergio.bracale@races.it'], FallbackRecipient::emails()->all());

        config(['services.fallback_recipient' => 'non-una-email']);
        $this->assertSame([], FallbackRecipient::emails()->all());

        config(['services.fallback_recipient' => null]);
        $this->assertSame([], FallbackRecipient::emails()->all());
    }
}
