<?php

namespace Tests\Unit;

use App\Models\ProcessTaskExecution;
use Tests\TestCase;

class ProcessTaskExecutionDeadlineTest extends TestCase
{
    public function test_effective_due_at_uses_mandatory_days_to_complete_when_set(): void
    {
        $execution = new ProcessTaskExecution([
            'started_at' => now()->subDays(5),
            'due_at' => now()->addDays(10), // il default del template, deve essere ignorato
            'mandatory_days_to_complete' => 3,
        ]);

        $this->assertTrue($execution->effectiveDueAt()->isSameDay(now()->subDays(2)));
    }

    public function test_effective_due_at_falls_back_to_due_at_when_not_set(): void
    {
        $dueAt = now()->addDays(10);

        $execution = new ProcessTaskExecution([
            'started_at' => now(),
            'due_at' => $dueAt,
        ]);

        $this->assertSame($dueAt->format('Y-m-d H:i:s'), $execution->effectiveDueAt()->format('Y-m-d H:i:s'));
    }

    public function test_is_overdue_respects_the_mandatory_days_deadline(): void
    {
        $overdue = new ProcessTaskExecution([
            'started_at' => now()->subDays(5),
            'mandatory_days_to_complete' => 3,
        ]);

        $onTime = new ProcessTaskExecution([
            'started_at' => now()->subDays(1),
            'mandatory_days_to_complete' => 3,
        ]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($onTime->isOverdue());
    }
}
