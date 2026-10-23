<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\Client;
use App\Models\EmployeeType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientEmployeeRolesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);
    }

    public function test_client_form_persists_employee_roles(): void
    {
        $role = EmployeeType::create(['name' => 'consulente esterno', 'is_external' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Studio Bianchi',
                'email' => 'studio@example.com',
                'employee_roles' => [$role->name],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::query()->where('email', 'studio@example.com')->sole();
        $this->assertSame([$role->name], $client->employee_roles);

        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->assertFormSet(['employee_roles' => [$role->name]]);
    }
}
