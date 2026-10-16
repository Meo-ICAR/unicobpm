<?php

namespace Tests\Feature;

use App\Models\BusinessFunction;
use App\Models\Employee;
use App\Models\EmployeeType;
use App\Models\Process;
use App\Models\ProcessInstance;
use App\Models\ProcessTaskItemAnswer;
use App\Models\ProcessTrigger;
use Database\Seeders\WhistleblowingProcessSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WhistleblowingProcessTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEY = ['X-Api-Key' => 'chiave-eventi'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['unico-core.bridge.api_key' => 'chiave-eventi', 'mail.default' => 'array']);
        $this->seed(WhistleblowingProcessSeeder::class);
    }

    private function member(string $email, ?string $role): Employee
    {
        $employee = Employee::create(['name' => 'Persona '.uniqid(), 'email' => $email]);
        BusinessFunction::where('code', 'WHISTLEBLOWING')->firstOrFail()->employees()->attach($employee->id, [
            'employee_type_id' => $role ? EmployeeType::where('name', $role)->value('id') : null,
        ]);

        return $employee;
    }

    private function event(): array
    {
        return [
            'event_id' => 'segnalazione.ricevuta:7', 'event' => 'segnalazione.ricevuta', 'source' => 'whistle', 'subject_ref' => 'WB-7',
            'data' => ['report_id' => 7, 'url' => 'https://whistle.example.com/admin', 'acknowledgement_due_at' => '2026-10-19', 'feedback_due_at' => '2027-01-12', 'hard_deadline' => '2027-01-12'],
        ];
    }

    private function sentTo(): array
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage()->getTo()[0]->getAddress())->all();
    }

    public function test_the_role_is_in_employee_types_and_the_seeder_is_idempotent(): void
    {
        $this->seed(WhistleblowingProcessSeeder::class);

        $this->assertSame(1, EmployeeType::where('name', 'responsabile whistleblowing')->count());
        $this->assertSame(1, BusinessFunction::where('code', 'WHISTLEBLOWING')->count());
        $this->assertSame(1, ProcessTrigger::where('external_event', 'segnalazione.ricevuta')->count());
        $this->assertSame(2, Process::where('code', 'PRC-WHISTLE')->firstOrFail()->tasks()->count());
    }

    public function test_a_report_notifies_only_the_members_with_the_whistleblowing_role(): void
    {
        $this->member('responsabile@example.com', 'responsabile whistleblowing');
        $this->member('altro-ruolo@example.com', null);

        $this->postJson('/api/events', $this->event(), self::KEY)->assertStatus(202)->assertJson(['status' => 'processed', 'started' => 1]);

        $this->assertSame(['responsabile@example.com'], $this->sentTo());
        $message = Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $body = quoted_printable_decode($message->getBody()->bodyToString());
        $this->assertSame('Nuova segnalazione whistleblowing ricevuta (whistle:WB-7)', $message->getSubject());
        $this->assertStringContainsString('https://whistle.example.com/admin', $body);
        $this->assertStringContainsString('2026-10-19', $body);
        $this->assertStringContainsString('riservatezza', $body);
        $instance = ProcessInstance::sole();
        $this->assertSame('whistle:WB-7', $instance->external_ref);
        $this->assertSame('2027-01-12', $instance->hard_deadline_at->toDateString());
        $this->assertSame('Presa in carico e avviso di ricevimento', $instance->currentTask->name);
        $this->assertStringContainsString('responsabile@example.com', ProcessTaskItemAnswer::sole()->value_text);
        $this->assertSame('in_progress', $instance->status);
    }

    public function test_every_holder_of_the_role_is_notified_and_without_holders_the_function_email_is_used(): void
    {
        $this->member('uno@example.com', 'responsabile whistleblowing');
        $this->member('due@example.com', 'responsabile whistleblowing');
        $this->postJson('/api/events', $this->event(), self::KEY)->assertStatus(202);
        $this->assertEqualsCanonicalizing(['uno@example.com', 'due@example.com'], $this->sentTo());

        Mail::mailer('array')->getSymfonyTransport()->flush();
        Employee::query()->whereIn('email', ['uno@example.com', 'due@example.com'])->get()->each(fn ($e) => BusinessFunction::where('code', 'WHISTLEBLOWING')->first()->employees()->detach($e->id));
        BusinessFunction::where('code', 'WHISTLEBLOWING')->update(['email' => 'whistle@example.com']);
        $second = $this->event();
        $second['event_id'] = 'segnalazione.ricevuta:8';

        $this->postJson('/api/events', $second, self::KEY)->assertStatus(202);

        $this->assertSame(['whistle@example.com'], $this->sentTo());
    }

    public function test_the_report_content_never_travels_with_the_event_or_the_email(): void
    {
        $this->member('responsabile@example.com', 'responsabile whistleblowing');
        $event = $this->event();

        $this->postJson('/api/events', $event, self::KEY)->assertStatus(202);

        $stored = json_encode(ProcessInstance::sole()->external_data);
        $this->assertStringNotContainsString('description', $stored);
        $this->assertStringNotContainsString('tracking', $stored);
    }
}
