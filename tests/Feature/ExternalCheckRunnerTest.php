<?php

namespace Tests\Feature;

use App\Enums\Severity;
use App\Models\EmailTemplate;
use App\Models\ProcessTask;
use App\Models\ProcessTaskItem;
use App\Services\EmailSendingService;
use App\Services\ExternalAppResolver;
use App\Services\ExternalCheckRunner;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Gli EmailTemplate vivono su mysql_unicooam (DB reale, vedi BPM-DOMAIN-SPEC §9):
 * i dati di test usano un code dedicato e vengono ripuliti a fine test.
 */
class ExternalCheckRunnerTest extends TestCase
{
    private const CODE = 'TEST_EXTERNAL_CHECK';

    protected function setUp(): void
    {
        parent::setUp();

        EmailTemplate::where('code', self::CODE)->delete();

        foreach (Severity::cases() as $severity) {
            EmailTemplate::create([
                'code' => self::CODE,
                'name' => "Test {$severity->value}",
                'subject' => "Subject {$severity->value}: {value}",
                'body' => "Body {$severity->value} {severity}",
                'is_active' => true,
                'severity' => $severity,
            ]);
        }
    }

    protected function tearDown(): void
    {
        EmailTemplate::where('code', self::CODE)->delete();

        parent::tearDown();
    }

    private function item(): ProcessTaskItem
    {
        $item = new ProcessTaskItem(['config' => [
            'app' => 'proforma',
            'command' => 'clienti:check-missing-piva',
            'email_template_code' => self::CODE,
        ]]);

        return $item->setRelation('task', new ProcessTask);
    }

    /**
     * @param  array<int, string>  $recipients  Destinatari degli avvisi (ruoli della severity).
     * @param  array<int, string>  $managers  Destinatari del rapporto d'esito (ruolo A).
     */
    private function runnerNotifying(array $recipients, ?callable $assertSend = null, array $managers = []): ExternalCheckRunner
    {
        $sender = $this->mock(EmailSendingService::class, function (MockInterface $mock) use ($assertSend) {
            $assertSend ? $assertSend($mock) : $mock->shouldNotReceive('send');
        });

        return \Mockery::mock(ExternalCheckRunner::class, [app(ExternalAppResolver::class), $sender])
            ->makePartial()
            ->shouldReceive('recipientsForRoles')
            ->andReturnUsing(fn ($task, array $roles) => collect($roles === ['A'] ? $managers : $recipients))
            ->getMock();
    }

    public function test_resolves_template_by_code_and_severity(): void
    {
        $template = $this->item()->resolveCheckEmailTemplate(Severity::Warning);

        $this->assertSame(Severity::Warning, $template->severity);
    }

    public function test_resolves_highest_severity_template_when_severity_is_absent(): void
    {
        $template = $this->item()->resolveCheckEmailTemplate(null);

        $this->assertSame(Severity::Alert, $template->severity);
    }

    public function test_resolves_no_template_when_severity_has_none(): void
    {
        EmailTemplate::where('code', self::CODE)->where('severity', Severity::Regular->value)->delete();

        $this->assertNull($this->item()->resolveCheckEmailTemplate(Severity::Regular));
    }

    public function test_sends_template_matching_returned_severity(): void
    {
        Http::fake(['*/api/checks/*' => Http::response(['value' => 3, 'severity' => 'warning'])]);

        $runner = $this->runnerNotifying(['a@x.it'], function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with('a@x.it', 'Subject warning: 3', 'Body warning Warning');
        });

        $summary = $runner->run($this->item());

        $this->assertStringContainsString('severity warning', $summary);
    }

    public function test_falls_back_to_highest_severity_template_when_response_has_no_severity(): void
    {
        Http::fake(['*/api/checks/*' => Http::response(['value' => 7])]);

        $runner = $this->runnerNotifying(['a@x.it'], function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with('a@x.it', 'Subject alert: 7', 'Body alert Alert');
        });

        $runner->run($this->item());
    }

    public function test_sends_nothing_when_there_are_no_recipients(): void
    {
        Http::fake(['*/api/checks/*' => Http::response(['value' => 2, 'severity' => 'regular'])]);

        $summary = $this->runnerNotifying([])->run($this->item());

        $this->assertStringContainsString('nessun destinatario', $summary);
    }

    public function test_sends_nothing_when_severity_is_ok(): void
    {
        Http::fake(['*/api/checks/*' => Http::response(['value' => 0, 'severity' => 'ok'])]);

        $summary = $this->runnerNotifying(['a@x.it'])->run($this->item());

        $this->assertStringContainsString('nessuna email da inviare', $summary);
    }

    public function test_sends_nothing_when_api_fails(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $summary = $this->runnerNotifying(['a@x.it'])->run($this->item());

        $this->assertStringContainsString('non disponibile', $summary);
    }

    public function test_failed_delivery_is_reported_in_summary_and_does_not_stop_other_recipients(): void
    {
        Http::fake(['*/api/checks/*' => Http::response(['value' => 3, 'severity' => 'regular'])]);

        $runner = $this->runnerNotifying(['bad@x.it', 'ok@x.it'], function (MockInterface $mock) {
            $mock->shouldReceive('send')->with('bad@x.it', \Mockery::any(), \Mockery::any())->once()->andThrow(new \RuntimeException('SMTP down'));
            $mock->shouldReceive('send')->with('ok@x.it', \Mockery::any(), \Mockery::any())->once();
        });

        $summary = $runner->run($this->item());

        $this->assertStringContainsString('email inviata a ok@x.it', $summary);
        $this->assertStringContainsString('FALLITO per: bad@x.it (SMTP down)', $summary);
    }

    public function test_managers_receive_an_outcome_report_for_warning_and_alert(): void
    {
        Http::fake(['*/api/checks/*' => Http::response(['value' => 3, 'severity' => 'alert'])]);

        $runner = $this->runnerNotifying(['a@x.it'], function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with('a@x.it', 'Subject alert: 3', 'Body alert Alert');
            $mock->shouldReceive('send')->once()->with('boss@x.it', \Mockery::pattern('/^Esito invio avvisi \(Alert\)/'), \Mockery::pattern('/Email inviate a: a@x\.it/'));
        }, managers: ['boss@x.it']);

        $runner->run($this->item());
    }

    public function test_no_outcome_report_for_regular_severity(): void
    {
        Http::fake(['*/api/checks/*' => Http::response(['value' => 1, 'severity' => 'regular'])]);

        $runner = $this->runnerNotifying(['a@x.it'], function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->with('a@x.it', 'Subject regular: 1', 'Body regular Regular');
        }, managers: ['boss@x.it']);

        $runner->run($this->item());
    }

    public function test_failure_sending_the_outcome_report_does_not_break_the_run(): void
    {
        Http::fake(['*/api/checks/*' => Http::response(['value' => 3, 'severity' => 'warning'])]);

        $runner = $this->runnerNotifying(['a@x.it'], function (MockInterface $mock) {
            $mock->shouldReceive('send')->with('a@x.it', \Mockery::any(), \Mockery::any())->once();
            $mock->shouldReceive('send')->with('boss@x.it', \Mockery::any(), \Mockery::any())->once()->andThrow(new \RuntimeException('SMTP down'));
        }, managers: ['boss@x.it']);

        $this->assertStringContainsString('email inviata a a@x.it', $runner->run($this->item()));
    }
}
