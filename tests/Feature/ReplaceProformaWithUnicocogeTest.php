<?php

namespace Tests\Feature;

use App\Models\OutboundCall;
use App\Models\Process;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReplaceProformaWithUnicocogeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Proforma si chiama UnicoCoge: la migration porta su unicocoge i processi, le azioni e le chiamate non ancora
     * consegnate, e lascia com'è lo storico delle chiamate già consegnate.
     */
    public function test_the_migration_moves_saved_references_from_proforma_to_unicocoge(): void
    {
        $process = Process::create(['name' => 'Scadenziario', 'code' => 'PRC-T-COGE', 'is_active' => true, 'completion_write_app' => 'proforma']);
        $other = Process::create(['name' => 'Altro', 'code' => 'PRC-T-OAM', 'is_active' => true, 'completion_write_app' => 'unicooam']);
        $task = ProcessTask::create(['process_id' => $process->id, 'name' => 'Scadenze', 'ordine' => 10]);
        $check = ProcessTaskItem::create(['process_task_id' => $task->id, 'name' => 'P.IVA', 'action_type' => 'external_check', 'ordine' => 1, 'config' => ['command' => 'clienti:check-missing-piva', 'app' => 'proforma']]);
        $email = ProcessTaskItem::create(['process_task_id' => $task->id, 'name' => 'Email', 'action_type' => 'automated_email', 'ordine' => 2, 'config' => ['note' => 'scritto da proforma']]);
        $failed = OutboundCall::create(['event' => 'completion_write', 'app' => 'proforma', 'method' => 'PATCH', 'path' => '/x', 'idempotency_key' => 'f', 'status' => 'failed', 'attempts' => 6]);
        $pending = OutboundCall::create(['event' => 'completion_write', 'app' => 'proforma', 'method' => 'PATCH', 'path' => '/z', 'idempotency_key' => 'p', 'status' => 'pending', 'attempts' => 0, 'meta' => ['app' => 'proforma', 'field' => 'paid_at']]);
        $sent = OutboundCall::create(['event' => 'completion_write', 'app' => 'proforma', 'method' => 'PATCH', 'path' => '/y', 'idempotency_key' => 's', 'status' => 'sent', 'attempts' => 1]);

        $migration = require database_path('migrations/2026_10_23_162525_replace_proforma_with_unicocoge.php');
        $migration->up();

        $this->assertSame('unicocoge', $process->fresh()->completion_write_app);
        $this->assertSame('unicooam', $other->fresh()->completion_write_app);
        $this->assertEquals(['command' => 'clienti:check-missing-piva', 'app' => 'unicocoge'], $check->fresh()->config);
        $this->assertEquals(['note' => 'scritto da proforma'], $email->fresh()->config);
        $this->assertSame('unicocoge', $failed->fresh()->app);
        $this->assertSame('unicocoge', $pending->fresh()->app);
        $this->assertEquals(['app' => 'unicocoge', 'field' => 'paid_at'], $pending->fresh()->meta);
        $this->assertSame('proforma', $sent->fresh()->app);

        $migration->down();

        $this->assertSame('proforma', $process->fresh()->completion_write_app);
        $this->assertSame('unicooam', $other->fresh()->completion_write_app);
        $this->assertEquals(['command' => 'clienti:check-missing-piva', 'app' => 'proforma'], $check->fresh()->config);
        $this->assertSame('proforma', $failed->fresh()->app);
        $this->assertEquals(['app' => 'proforma', 'field' => 'paid_at'], $pending->fresh()->meta);
        $this->assertSame('proforma', $sent->fresh()->app);
    }
}
