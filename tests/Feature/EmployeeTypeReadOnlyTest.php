<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeTypes\EmployeeTypeResource;
use App\Filament\Resources\EmployeeTypes\Pages\ViewEmployeeType;
use App\Filament\Resources\EmployeeTypes\RelationManagers\PermissionsRelationManager;
use App\Models\EmployeeType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeTypeReadOnlyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_roles_are_only_viewed_here_and_edited_in_unicooam_while_bpm_permissions_stay_editable(): void
    {
        config(['services.apps.unicooam.url' => 'https://unicooam.test/']);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $role = EmployeeType::query()->create(['name' => 'compliance', 'code' => 'COMPLIANCE', 'description' => 'Funzione Compliance']);

        $this->assertSame(['index', 'view'], array_keys(EmployeeTypeResource::getPages()));

        Livewire::test(ViewEmployeeType::class, ['record' => $role->getKey()])
            ->assertActionHasUrl('modificaInUnicooam', 'https://unicooam.test/admin/employee-types/'.$role->getKey().'/edit')
            ->assertSee('Funzione Compliance');

        $this->assertFalse((new PermissionsRelationManager)->isReadOnly());
    }
}
