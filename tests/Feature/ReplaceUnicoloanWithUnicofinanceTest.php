<?php

namespace Tests\Feature;

use App\Models\OutboundCall;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use App\Services\ExternalAppResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReplaceUnicoloanWithUnicofinanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_default_app_is_unicofinance(): void
    {
        $this->assertSame('unicofinance', ExternalAppResolver::DEFAULT_APP);
    }

    /**
     * unicoloan è dismesso: la migration porta su unicofinance i processi, le azioni e le chiamate non ancora consegnate,
     * e lascia com'è lo storico delle chiamate già consegnate.
     */
    public function test_the_migration_moves_saved_references_from_unicoloan_to_unicofinance(): void
    {
        $process = Process::create(['name' => 'Onboarding', 'code' => 'PRC-T-ONB', 'is_active' => true, 'completion_write_app' => 'unicoloan']);
        $other = Process::create(['name' => 'Altro', 'code' => 'PRC-T-OAM', 'is_active' => true, 'completion_write_app' => 'unicooam']);
        $task = ProcessTask::create(['process_id' => $process->id, 'name' => 'Invio', 'ordine' => 10]);
        $blacklist = ProcessTaskItem::create(['process_task_id' => $task->id, 'name' => 'Blacklist', 'action_type' => 'blacklist_check', 'ordine' => 1, 'config' => ['pratica_id_field' => 'subject_id', 'app' => 'unicoloan']]);
        $email = ProcessTaskItem::create(['process_task_id' => $task->id, 'name' => 'Email', 'action_type' => 'automated_email', 'ordine' => 2, 'config' => ['note' => 'scritto da unicoloan']]);
        $pending = OutboundCall::create(['event' => 'completion_write', 'app' => 'unicoloan', 'method' => 'PATCH', 'path' => '/x', 'idempotency_key' => 'p', 'status' => 'failed', 'attempts' => 6]);
        $sent = OutboundCall::create(['event' => 'completion_write', 'app' => 'unicoloan', 'method' => 'PATCH', 'path' => '/y', 'idempotency_key' => 's', 'status' => 'sent', 'attempts' => 1]);

        (require database_path('migrations/2026_10_18_140000_replace_unicoloan_with_unicofinance.php'))->up();

        $this->assertSame('unicofinance', $process->fresh()->completion_write_app);
        $this->assertSame('unicooam', $other->fresh()->completion_write_app);
        $this->assertSame(['pratica_id_field' => 'subject_id', 'app' => 'unicofinance'], $blacklist->fresh()->config);
        $this->assertSame(['note' => 'scritto da unicoloan'], $email->fresh()->config);
        $this->assertSame('unicofinance', $pending->fresh()->app);
        $this->assertSame('unicoloan', $sent->fresh()->app);
    }
}
