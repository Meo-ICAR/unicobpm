<?php

namespace Tests\Feature;

use App\Filament\Resources\Resources\Pages\ListResources;
use App\Models\Resource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Column;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourcesTableTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_column_is_sortable_and_the_columns_have_filters(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Resource::query()->create(['app_name' => 'UnicoBPM', 'key' => 'a', 'name' => 'Alfa', 'group' => 'Processi', 'min_plan' => 'BASE']);
        Resource::query()->create(['app_name' => 'UnicoOAM', 'key' => 'b', 'name' => 'Beta', 'group' => 'Sistema', 'min_plan' => 'FULL']);

        $list = Livewire::test(ListResources::class);

        foreach (['app_name', 'name', 'group', 'min_plan'] as $column) {
            $list->assertTableColumnExists($column, fn (Column $tableColumn): bool => $tableColumn->isSortable());
        }

        $list->sortTable('name', 'desc')->assertCanSeeTableRecords(Resource::orderByDesc('name')->get(), inOrder: true)
            ->filterTable('app_name', 'UnicoOAM')->assertCountTableRecords(1)
            ->removeTableFilters()
            ->filterTable('group', 'Processi')->assertCountTableRecords(1)
            ->removeTableFilters()
            ->filterTable('min_plan', 'FULL')->assertCountTableRecords(1);
    }
}
