<?php

namespace Tests\Feature;

use App\Models\Process;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProcessEligibleRecordsCountTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Uses a throwaway local model/table registered in the morph map instead
     * of a real target (Fornitore/Clienti live on the shared, non-test
     * `proforma` database) so the count is deterministic and doesn't depend
     * on live data.
     */
    public function test_counts_records_matching_trigger_and_exclude_criteria(): void
    {
        Schema::create('test_eligible_subjects', function (Blueprint $table) {
            $table->id();
            $table->timestamp('stipulated_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });

        Relation::morphMap(['test-eligible-subject' => TestEligibleSubject::class]);

        TestEligibleSubject::create(['stipulated_at' => null, 'dismissed_at' => null]); // eleggibile
        TestEligibleSubject::create(['stipulated_at' => null, 'dismissed_at' => null]); // eleggibile
        TestEligibleSubject::create(['stipulated_at' => now(), 'dismissed_at' => null]); // escluso: stipulated_at valorizzato

        $process = Process::create([
            'name' => 'Test Onboarding',
            'code' => 'PRC-TEST-COUNT',
            'is_active' => true,
            'target_model' => 'test-eligible-subject',
            'exclude_field' => 'stipulated_at',
            'exclude_state' => 'filled',
        ]);

        $this->assertSame(2, $process->eligibleRecordsCount());
    }

    public function test_returns_null_when_no_target_model_is_configured(): void
    {
        $process = Process::create(['name' => 'Senza Target', 'code' => 'PRC-TEST-NO-TARGET', 'is_active' => true]);

        $this->assertNull($process->eligibleRecordsCount());
    }
}

class TestEligibleSubject extends Model
{
    protected $table = 'test_eligible_subjects';

    protected $guarded = [];
}
