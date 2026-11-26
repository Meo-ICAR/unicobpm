<?php

namespace Tests\Feature;

use App\Jobs\SendOutboundCall;
use App\Models\OutboundCall;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTrigger;
use App\Services\ExternalCommandRunner;
use Database\Seeders\AuditVerificaIspettivaProcessSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditVerificaIspettivaProcessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_il_trigger_avvia_il_processo_solo_per_l_intento_audit_periodico(): void
    {
        $this->seed(AuditVerificaIspettivaProcessSeeder::class);
        $this->seed(AuditVerificaIspettivaProcessSeeder::class); // idempotente

        $trigger = ProcessTrigger::query()->where('external_event', 'email.richiesta')->sole();

        $this->assertEquals([['field' => 'richiesta', 'operator' => '=', 'value' => 'audit_periodico']], $trigger->conditions);
        $this->assertSame('PRC-AUDIT-RETE', Process::findOrFail($trigger->process_id)->code);
    }

    public function test_il_comando_in_unicooam_riceve_l_email_e_i_produttori_della_pratica(): void
    {
        Queue::fake();
        $this->seed(AuditVerificaIspettivaProcessSeeder::class);

        $item = Process::query()->where('code', 'PRC-AUDIT-RETE')->sole()->tasks()->where('ordine', 20)->sole()
            ->processTaskItems()->where('action_type', 'external_command')->sole();
        $instance = ProcessInstance::create([
            'process_id' => $item->task->process_id,
            'status' => 'in_progress',
            'external_data' => ['produttori_ids' => '103,117'],
        ]);
        // L'email vive in UnicoPEC: nel test non esiste in incoming_emails, quindi l'id si imposta solo in memoria.
        $instance->incoming_email_id = 467;

        app(ExternalCommandRunner::class)->run($item, $instance);

        $call = OutboundCall::query()->sole();
        $this->assertSame('/api/commands/audits:open-from-email', $call->path);
        $this->assertSame('467', $call->payload['options']['email']);
        $this->assertSame('103,117', $call->payload['options']['produttori']);
        Queue::assertPushed(SendOutboundCall::class, 1);
    }
}
