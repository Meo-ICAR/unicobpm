<?php

namespace Tests\Feature;

use App\Filament\Pages\AssistenteAi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssistenteAiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_prompt_fails_validation_without_calling_the_agent(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AssistenteAi::class)
            ->fillForm(['prompt' => ''])
            ->call('send')
            ->assertHasFormErrors(['prompt' => 'required']);
    }

    public function test_filled_prompt_updates_form_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AssistenteAi::class)
            ->fillForm(['prompt' => 'come emetto un proforma?'])
            ->assertFormSet(['prompt' => 'come emetto un proforma?'])
            ->assertHasNoFormErrors();
    }
}
