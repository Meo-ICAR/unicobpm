<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\Process;
use Database\Seeders\ComplianceDocumentazioneProcessSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ComplianceProcessSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_compliance_process_checks_documents_and_stale_purchase_invoices_and_is_idempotent(): void
    {
        $this->seed(ComplianceDocumentazioneProcessSeeder::class);
        $this->seed(ComplianceDocumentazioneProcessSeeder::class);

        $process = Process::query()->where('code', 'PRC-COMPLIANCE')->sole();
        $checks = $process->tasks()->orderBy('ordine')->get()
            ->flatMap(fn ($task) => $task->processTaskItems->map(fn ($item) => $item->config['app'].':'.$item->config['command']));

        $this->assertSame(
            [
                'unicooam:documents:check-expired', 'unicooam:documents:check-expiring', 'unicocoge:purchase-invoices:check-stale',
                'unicocoge:provvigioni:check-maturate', 'unicocoge:provvigioni:check-maturate', 'unicocoge:proformas:send-pending',
            ],
            $checks->all(),
        );
        $this->assertStringContainsString('25 giorni', $process->description);
        $this->assertSame(3, EmailTemplate::query()->where('code', 'COMPL_FATTURE_ACQUISTO')->count());
    }
}
