<?php

namespace Tests\Feature;

use App\Filament\Resources\ExternalEvents\Pages\ListExternalEvents;
use App\Models\ExternalEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExternalEventResourceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.env' => 'local']);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_the_backup_outcome_is_summarised_and_events_can_be_filtered(): void
    {
        $backup = ExternalEvent::create(['event_id' => 'b1', 'name' => 'backup.esito', 'source' => 'backups3', 'subject_ref' => 'vps:2026-10-12', 'status' => 'ignored', 'data' => [
            'status' => 'failed', 'databases' => [['name' => 'a', 'ok' => true], ['name' => 'b', 'ok' => false]], 'databases_failed' => ['b'], 'databases_warnings' => ['proforma'],
        ]]);
        $pratica = ExternalEvent::create(['event_id' => 'p1', 'name' => 'pratica.perfezionata', 'source' => 'unicoagent', 'status' => 'processed', 'started_count' => 1, 'data' => ['x' => 1]]);

        Livewire::test(ListExternalEvents::class)
            ->assertCanSeeTableRecords([$backup, $pratica])
            ->assertSee('Backup FALLITO · 2 database · non salvati: b · avvisi: proforma')
            ->filterTable('name', 'backup.esito')
            ->assertCanSeeTableRecords([$backup])
            ->assertCanNotSeeTableRecords([$pratica]);
    }
}
