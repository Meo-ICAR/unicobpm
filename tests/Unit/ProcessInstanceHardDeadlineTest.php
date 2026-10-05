<?php

namespace Tests\Unit;

use App\Models\ProcessInstance;
use Tests\TestCase;

class ProcessInstanceHardDeadlineTest extends TestCase
{
    public function test_is_past_hard_deadline_only_when_in_progress_and_overdue(): void
    {
        $overdueInProgress = new ProcessInstance([
            'status' => 'in_progress',
            'hard_deadline_at' => now()->subDay(),
        ]);

        $overdueButCompleted = new ProcessInstance([
            'status' => 'completed',
            'hard_deadline_at' => now()->subDay(),
        ]);

        $inProgressNotYetDue = new ProcessInstance([
            'status' => 'in_progress',
            'hard_deadline_at' => now()->addDay(),
        ]);

        $inProgressNoDeadline = new ProcessInstance([
            'status' => 'in_progress',
        ]);

        $this->assertTrue($overdueInProgress->isPastHardDeadline());
        $this->assertFalse($overdueButCompleted->isPastHardDeadline());
        $this->assertFalse($inProgressNotYetDue->isPastHardDeadline());
        $this->assertFalse($inProgressNoDeadline->isPastHardDeadline());
    }
}
