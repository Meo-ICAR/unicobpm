<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceTablesSortAndFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_sortable_column_and_every_filter_of_every_resource_table_runs(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $failures = [];
        $checked = 0;

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $pages = $resource::getPages();

            if (! isset($pages['index'])) {
                continue;
            }

            $page = $pages['index']->getPage();

            try {
                $list = Livewire::test($page);
                $table = $list->instance()->getTable();

                foreach ($table->getColumns() as $name => $column) {
                    if ($column->isSortable()) {
                        $list->sortTable((string) $name)->sortTable((string) $name, 'desc');
                        $checked++;
                    }
                }

                foreach ($table->getFilters(true) as $name => $filter) {
                    $value = match (true) {
                        $filter instanceof TernaryFilter => true,
                        $filter instanceof SelectFilter => array_key_first($filter->getOptions()),
                        $filter instanceof Filter && str_contains((string) $name, 'col_') => ['value' => 'a', 'dal' => '2000-01-01', 'al' => '2100-01-01'],
                        default => null,
                    };

                    if ($value !== null) {
                        $list->filterTable($name, $value)->removeTableFilters();
                        $checked++;
                    }
                }
            } catch (\Throwable $e) {
                $failures[] = $resource.': '.substr($e->getMessage(), 0, 200);
            }
        }

        $this->assertSame([], $failures);
        $this->assertGreaterThan(100, $checked);
    }
}
