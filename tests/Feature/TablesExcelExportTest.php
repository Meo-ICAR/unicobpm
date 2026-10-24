<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TablesExcelExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_every_resource_table_has_the_excel_export_and_it_downloads_a_spreadsheet(): void
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

            try {
                $list = Livewire::test($pages['index']->getPage());

                $action = TestAction::make('excelExport')->table();

                $list->assertActionExists($action)->callAction($action)->assertHasNoErrors();
                $checked++;
            } catch (\Throwable $e) {
                $failures[] = $resource.': '.substr($e->getMessage(), 0, 200);
            }
        }

        $this->assertSame([], $failures);
        $this->assertGreaterThan(15, $checked);
    }
}
