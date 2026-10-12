<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Models\BusinessFunction;
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

    public function test_attaching_client_to_business_function_syncs_employee_role(): void
    {
        $role = EmployeeType::create(['name' => 'responsabile whistleblowing', 'is_external' => true]);
        $client = Client::create(['name' => 'Consulente Rossi', 'email' => 'rossi@example.com']);
        $function = BusinessFunction::create(['name' => 'Compliance', 'code' => 'CMP-'.uniqid()]);

        $member = \App\Models\BusinessFunctionMember::create([
            'business_function_id' => $function->id,
            'member_type' => $client->getMorphClass(),
            'member_id' => $client->id,
            'employee_type_id' => $role->id,
        ]);

        $this->assertSame([$role->name], $client->fresh()->employee_roles);

        $other = EmployeeType::create(['name' => 'audit', 'is_external' => true]);
        $member->update(['employee_type_id' => $other->id]);

        $this->assertEqualsCanonicalizing([$role->name, $other->name], $client->fresh()->employee_roles);
    }
}
