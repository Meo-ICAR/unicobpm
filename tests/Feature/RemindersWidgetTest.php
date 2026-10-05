<?php

namespace Tests\Feature;

use App\Filament\Widgets\RemindersWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RemindersWidgetTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * A User with no linked Employee/Client profile (the morphTo `profile`
     * relation) has no RACI membership to resolve tasks against — must
     * degrade to "nothing to do" instead of erroring.
     */
    public function test_shows_no_work_for_a_user_without_a_profile(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(RemindersWidget::class)
            ->assertSee('Nessuna pratica in carico o da prendere in carico al momento.');
    }
}
