<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Support\DomainStates;

class DomainStatesTest extends TestCase
{
    public function test_application_statuses_count(): void
    {
        $statuses = DomainStates::getApplicationStatuses();
        $this->assertCount(6, $statuses);
        $this->assertContains(DomainStates::APP_PENDING, $statuses);
        $this->assertContains(DomainStates::APP_REVIEWING, $statuses);
        $this->assertContains(DomainStates::APP_INTERVIEW, $statuses);
        $this->assertContains(DomainStates::APP_PASSED, $statuses);
        $this->assertContains(DomainStates::APP_REJECTED, $statuses);
        $this->assertContains(DomainStates::APP_WITHDRAWN, $statuses);
    }
}
