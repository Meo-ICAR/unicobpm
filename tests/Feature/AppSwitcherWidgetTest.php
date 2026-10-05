<?php

namespace Tests\Feature;

use App\Filament\Widgets\AppSwitcherWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AppSwitcherWidgetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lists_apps_that_report_a_matching_account(): void
    {
        $user = User::factory()->create(['email' => 'agente@example.com']);
        $this->actingAs($user);

        config()->set('services.apps', [
            'unicoloan' => ['url' => 'https://unicoloan.test', 'label' => 'UnicoLoan'],
            'unicooam' => ['url' => 'https://unicooam.test', 'label' => 'UnicoOAM'],
        ]);

        Http::fake([
            'https://unicoloan.test/api/users/lookup*' => Http::response(['exists' => true]),
            'https://unicooam.test/api/users/lookup*' => Http::response(['exists' => false]),
        ]);

        Livewire::test(AppSwitcherWidget::class)
            ->assertSee('UnicoLoan')
            ->assertDontSee('UnicoOAM');
    }

    public function test_shows_an_empty_state_when_no_other_app_has_a_matching_account(): void
    {
        $user = User::factory()->create(['email' => 'agente@example.com']);
        $this->actingAs($user);

        config()->set('services.apps', [
            'unicoloan' => ['url' => 'https://unicoloan.test', 'label' => 'UnicoLoan'],
        ]);

        Http::fake([
            'https://unicoloan.test/api/users/lookup*' => Http::response(['exists' => false]),
        ]);

        Livewire::test(AppSwitcherWidget::class)
            ->assertSee('Nessun altro account trovato con questa email.');
    }
}
