<?php

namespace Tests\Feature;

use App\Filament\Widgets\AppSwitcherWidget;
use App\Models\User;
use App\Services\SsoTokenBroker;
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
            'unicofinance' => ['url' => 'https://unicofinance.test', 'label' => 'UnicoFinance'],
            'unicooam' => ['url' => 'https://unicooam.test', 'label' => 'UnicoOAM'],
        ]);

        Http::fake([
            'https://unicofinance.test/api/users/lookup*' => Http::response(['exists' => true]),
            'https://unicooam.test/api/users/lookup*' => Http::response(['exists' => false]),
        ]);

        Livewire::test(AppSwitcherWidget::class)
            ->assertSee('UnicoFinance')
            ->assertDontSee('UnicoOAM');
    }

    public function test_each_app_shows_its_current_brand_icon_next_to_the_name(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'agente@example.com']));

        $apps = ['unicofinance' => 'unico-finance', 'unicooam' => 'unico-oam', 'unicocoge' => 'unico-coge', 'unicogdpr' => 'unico-gdpr', 'daishboard' => 'dai-shboard', 'whistle' => 'unico-whistle', 'unicoaiact' => 'unico-aiact'];
        $fakes = [];

        foreach ($apps as $key => $icon) {
            config()->set("services.apps.{$key}", ['url' => "https://{$key}.test", 'label' => "App {$key}"]);
            $fakes["https://{$key}.test/api/users/lookup*"] = Http::response(['exists' => true]);
        }

        Http::fake($fakes);

        $widget = Livewire::test(AppSwitcherWidget::class);

        foreach ($apps as $key => $icon) {
            $widget->assertSee("App {$key}")->assertSeeHtml("brand/icons/{$icon}-64.png");
            $this->assertFileExists(public_path("brand/icons/{$icon}-64.png"));
        }
    }

    public function test_shows_an_empty_state_when_no_other_app_has_a_matching_account(): void
    {
        $user = User::factory()->create(['email' => 'agente@example.com']);
        $this->actingAs($user);

        config()->set('services.apps', [
            'unicofinance' => ['url' => 'https://unicofinance.test', 'label' => 'UnicoFinance'],
        ]);

        Http::fake([
            'https://unicofinance.test/api/users/lookup*' => Http::response(['exists' => false]),
        ]);

        Livewire::test(AppSwitcherWidget::class)
            ->assertSee('Nessun altro account trovato con questa email.');
    }

    public function test_the_switch_to_unicofinance_sends_the_token_in_a_post_form(): void
    {
        $user = User::factory()->create(['email' => 'agente@example.com']);
        config()->set('services.apps', [
            'unicofinance' => ['url' => 'https://unicofinance.test', 'label' => 'UnicoFinance', 'sso_post' => true],
            'unicooam' => ['url' => 'https://unicooam.test', 'label' => 'UnicoOAM'],
        ]);

        $response = $this->actingAs($user)->post('/passa-a/unicofinance')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');

        $response->assertSee('<form method="POST" action="https://unicofinance.test/bpm-landing/dashboard">', false)
            ->assertSee('name="user_email" value="agente@example.com"', false);
        preg_match('/name="token" value="([^"]+)"/', $response->getContent(), $match);
        $this->assertTrue(app(SsoTokenBroker::class)->verifyAndConsume($match[1], 'agente@example.com'));

        $this->actingAs($user)->post('/passa-a/unicooam')->assertNotFound();
        config()->set('services.apps.unicofinance.url', null);
        $this->actingAs($user)->post('/passa-a/unicofinance')->assertNotFound();
        config()->set('services.apps.unicofinance.url', 'https://unicofinance.test');
        auth()->logout();
        $this->post('/passa-a/unicofinance')->assertRedirect('/admin/login');
    }

    public function test_an_app_that_takes_the_token_in_post_gets_a_form_and_never_a_link_with_the_token(): void
    {
        $user = User::factory()->create(['email' => 'agente@example.com']);
        $this->actingAs($user);
        config()->set('services.apps', [
            'unicofinance' => ['url' => 'https://unicofinance.test', 'label' => 'UnicoFinance', 'sso_post' => true],
        ]);
        Http::fake(['https://unicofinance.test/api/users/lookup*' => Http::response(['exists' => true])]);

        Livewire::test(AppSwitcherWidget::class)
            ->assertSeeHtml('<form method="POST" action="'.route('sso.passa', 'unicofinance').'">')
            ->assertSeeHtml('name="_token"')
            ->call('switchTo', 'unicofinance')
            ->assertStatus(404);
    }
}
