<?php

namespace Tests\Feature;

use App\Models\Process;
use App\Models\ProcessInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompletionWriteBackTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * When a ProcessInstance's status transitions to 'completed', and its Process
     * has `completion_write_field` configured (e.g. AGENT_ONBOARDING writing
     * 'stipulated_at'), ProcessInstanceObserver::updated() must write that field
     * onto the instance's subject. Uses a throwaway local model/table instead of
     * the real Fornitore (which lives on the shared, non-test `proforma`
     * database) so the test never touches production data.
     */
    public function test_writes_the_configured_field_onto_the_subject_when_the_instance_completes(): void
    {
        Schema::create('test_completion_subjects', function (Blueprint $table) {
            $table->id();
            $table->timestamp('stipulated_at')->nullable();
            $table->timestamps();
        });

        $subject = TestCompletionSubject::create();

        $process = Process::create([
            'name' => 'Onboarding Test',
            'code' => 'PRC-TEST-ONB',
            'is_active' => true,
            'completion_write_field' => 'stipulated_at',
            'completion_write_value' => 'now',
        ]);

        $instance = ProcessInstance::create([
            'process_id' => $process->id,
            'subject_type' => TestCompletionSubject::class,
            'subject_id' => $subject->id,
            'status' => 'in_progress',
        ]);

        $this->assertNull($subject->fresh()->stipulated_at);

        $instance->update(['status' => 'completed', 'completed_at' => now()]);

        $this->assertNotNull($subject->fresh()->stipulated_at);
    }

    public function test_does_not_write_anything_when_the_process_has_no_completion_write_field(): void
    {
        Schema::create('test_completion_subjects', function (Blueprint $table) {
            $table->id();
            $table->timestamp('stipulated_at')->nullable();
            $table->timestamps();
        });

        $subject = TestCompletionSubject::create();

        $process = Process::create([
            'name' => 'Processo Senza Write-Back',
            'code' => 'PRC-TEST-NOOP',
            'is_active' => true,
        ]);

        $instance = ProcessInstance::create([
            'process_id' => $process->id,
            'subject_type' => TestCompletionSubject::class,
            'subject_id' => $subject->id,
            'status' => 'in_progress',
        ]);

        $instance->update(['status' => 'completed', 'completed_at' => now()]);

        $this->assertNull($subject->fresh()->stipulated_at);
    }
}

class TestCompletionSubject extends Model
{
    protected $table = 'test_completion_subjects';

    protected $guarded = [];
}
