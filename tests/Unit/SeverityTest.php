<?php

namespace Tests\Unit;

use App\Enums\Severity;
use PHPUnit\Framework\TestCase;

class SeverityTest extends TestCase
{
    public function test_levels_increase_with_gravity(): void
    {
        $this->assertLessThan(Severity::Regular->level(), Severity::Ok->level());
        $this->assertLessThan(Severity::Warning->level(), Severity::Regular->level());
        $this->assertLessThan(Severity::Alert->level(), Severity::Warning->level());
    }

    public function test_raci_roles_to_notify_widen_with_severity(): void
    {
        $this->assertSame([], Severity::Ok->raciRolesToNotify());
        $this->assertSame(['R'], Severity::Regular->raciRolesToNotify());
        $this->assertSame(['R', 'A'], Severity::Warning->raciRolesToNotify());
        $this->assertSame(['R', 'A', 'C'], Severity::Alert->raciRolesToNotify());
    }

    public function test_unknown_value_is_not_a_severity(): void
    {
        $this->assertNull(Severity::tryFrom('critical'));
        $this->assertNull(Severity::tryFrom(''));
    }
}
