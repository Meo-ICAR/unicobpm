<?php

namespace Tests\Feature;

use App\Console\Commands\LinkUsersToProfiles;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Fornitore;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LinkUsersToProfilesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_email_domain_decides_the_profile_type(): void
    {
        $this->assertSame('employee', LinkUsersToProfiles::profileTypeFor('Mario.Rossi@races.it'));
        $this->assertSame('fornitore', LinkUsersToProfiles::profileTypeFor('agente@agenti.races.it'));
        $this->assertSame('client', LinkUsersToProfiles::profileTypeFor('studio@gmail.com'));
    }

    public function test_users_are_linked_to_existing_profiles_or_new_ones_and_consultants_become_companies(): void
    {
        $employee = Employee::create(['name' => 'ROSSI MARIO', 'email' => 'mario.rossi@races.it']);
        $existingClient = Client::create(['name' => 'Studio Beta', 'email' => 'beta@studio.it', 'is_company' => false]);

        $staff = User::factory()->create(['email' => 'Mario.Rossi@races.it']);
        $producer = User::factory()->create(['email' => 'agente@agenti.races.it', 'name' => 'Agente Uno']);
        $consultant = User::factory()->create(['email' => 'beta@studio.it']);
        $newConsultant = User::factory()->create(['email' => 'nuovo@studio.it', 'name' => 'Studio Nuovo']);

        $this->artisan('bpm:link-users-to-profiles')->assertSuccessful();
        $this->artisan('bpm:link-users-to-profiles')->assertSuccessful();

        $this->assertSame(['employee', $employee->id], [$staff->refresh()->profile_type, $staff->profile_id]);
        $this->assertSame($staff->id, $employee->refresh()->user_id);

        $this->assertSame('fornitore', $producer->refresh()->profile_type);
        $this->assertSame('Agente Uno', Fornitore::query()->findOrFail($producer->profile_id)->name);

        $this->assertSame(['client', $existingClient->id], [$consultant->refresh()->profile_type, $consultant->profile_id]);
        $this->assertTrue($existingClient->refresh()->is_company);

        $created = Client::query()->findOrFail($newConsultant->refresh()->profile_id);
        $this->assertTrue($created->is_company);
        $this->assertSame(1, Client::query()->where('email', 'nuovo@studio.it')->count(), 'Una seconda esecuzione non duplica le anagrafiche.');
    }
}
