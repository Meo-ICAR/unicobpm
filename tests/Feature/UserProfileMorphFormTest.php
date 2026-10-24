<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Fornitore;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserProfileMorphFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_user_profile_types_are_employee_consultant_and_producer(): void
    {
        Livewire::test(CreateUser::class)
            ->assertSee('Dipendente')
            ->assertSee('Consulente')
            ->assertSee('Produttore')
            ->assertDontSee('Cliente/Mediatore');
    }

    public function test_consultant_candidates_are_company_clients_only(): void
    {
        $consultant = Client::query()->create([
            'name' => 'Studio Alfa SRL',
            'is_company' => true,
        ]);
        $person = Client::query()->create([
            'name' => 'Mario Rossi Persona',
            'is_company' => false,
        ]);

        $ids = Client::query()->where('is_company', true)->pluck('id');

        $this->assertTrue($ids->contains($consultant->id));
        $this->assertFalse($ids->contains($person->id));
    }

    public function test_profile_models_resolve_to_expected_morph_aliases(): void
    {
        $this->assertSame('employee', (new Employee)->getMorphClass());
        $this->assertSame('client', (new Client)->getMorphClass());
        $this->assertSame('fornitore', (new Fornitore)->getMorphClass());
    }
}
