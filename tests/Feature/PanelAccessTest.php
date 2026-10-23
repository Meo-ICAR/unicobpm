<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_roles_access_the_panel_and_others_do_not(): void
    {
        $panel = Filament::getPanel('admin');

        foreach (User::STAFF_ROLES as $role) {
            $this->assertTrue(User::factory()->make(['role' => $role])->canAccessPanel($panel), $role);
        }

        $this->assertFalse(User::factory()->make(['role' => 'client'])->canAccessPanel($panel));
    }
}
